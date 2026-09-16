# -*- coding: utf-8 -*-
"""
Duplicate master detection for Site health.

Quick: same byte-size buckets → whole-file XXH3 clusters.
Full: content fingerprints ignoring tags/metadata:
  - audio: full demux-copy hash of the audio elementary stream (no duration gate;
    dual ID3/APE artwork cannot hide identical bitstreams)
  - video: duration bucket → demux-copy into temp/, hash shared prefix
  - stills: Pillow RGB pixels within matching pixel dimensions

Keep policy: campaign/playlist/brand/gallery/page refs win; if two+ members are
campaign-linked (or otherwise hard-referenced), cluster is conflict (warn only).
"""

from __future__ import print_function

import json
import os
import re
import shutil
import struct
import subprocess
import sys
import time
from collections import defaultdict

from paths import (
    AUDIO_MASTER_DIR,
    ROOT_DIR,
    SCRIPTS_DIR,
    SITE_HEALTH_TEMP_DIR,
    VISUAL_DELIVERY_DIR,
    VISUAL_MASTER_DIR,
)

if SCRIPTS_DIR not in sys.path:
    sys.path.insert(0, SCRIPTS_DIR)

try:
    import bandpromo_python_path
    bandpromo_python_path.ensure_vendor_on_sys_path()
except Exception:
    pass

import registry as reg

try:
    import xxhash
except ImportError:
    xxhash = None

_ASSET_ID_RE = re.compile(r'^ast_[0-9A-HJKMNP-TV-Z]{20}$', re.IGNORECASE)

# Cap each demux extract so Full check cannot dump multi-GB streams to disk.
# Within a group we still truncate further to min(actual demux sizes).
DEMUX_EXTRACT_MAX_BYTES = 8 * 1024 * 1024

# Container dirs that may hold asset_id references (local uses campaigns/).
_CONTAINER_DIRS = (
    ('playlists', os.path.join(ROOT_DIR, 'data', 'playlists')),
    ('campaigns', os.path.join(ROOT_DIR, 'data', 'campaigns')),
    ('releases', os.path.join(ROOT_DIR, 'data', 'releases')),
    ('brands', os.path.join(ROOT_DIR, 'data', 'brands')),
    ('galleries', os.path.join(ROOT_DIR, 'data', 'galleries')),
    ('pages', os.path.join(ROOT_DIR, 'data', 'pages')),
)


def _is_asset_id(value):
    return bool(_ASSET_ID_RE.match(str(value or '').strip()))


def _file_xxh3(path):
    if xxhash is None or not path or not os.path.isfile(path):
        return ''
    try:
        hasher = xxhash.xxh3_64()
        with open(path, 'rb') as handle:
            while True:
                chunk = handle.read(1024 * 1024)
                if not chunk:
                    break
                hasher.update(chunk)
        return hasher.hexdigest().lower()
    except Exception:
        return ''


def _xxh3_bytes(data):
    if xxhash is None or data is None:
        return ''
    try:
        return xxhash.xxh3_64(data).hexdigest().lower()
    except Exception:
        return ''


def _master_path(asset):
    kind = str(asset.get('kind') or '').strip().lower()
    master = os.path.basename(str(asset.get('master_filename') or '').strip())
    if not master:
        asset_id = str(asset.get('id') or asset.get('asset_id') or '').strip()
        fmt = str(asset.get('master_format') or '').strip().lower()
        if asset_id and fmt:
            master = '{0}.{1}'.format(asset_id, fmt)
    if not master:
        return ''
    if kind == 'audio':
        return os.path.join(AUDIO_MASTER_DIR, master)
    if kind == 'visual':
        return os.path.join(VISUAL_MASTER_DIR, master)
    return ''


def _candidate_assets(registry):
    """Audio + visual stills + visual video masters."""
    audio, visual, _sfx, _other = reg.assets_by_kind(registry)
    out = []
    for asset in audio:
        item = dict(asset)
        item['id'] = str(asset.get('id') or asset.get('asset_id') or '').strip()
        item['kind'] = 'audio'
        if _is_asset_id(item['id']):
            out.append(item)
    for asset in visual:
        media_type = str(asset.get('media_type') or '').strip().lower()
        fmt = str(asset.get('master_format') or '').strip().lower()
        is_video = media_type == 'video' or fmt in ('mkv', 'mp4', 'webm', 'mov')
        item = dict(asset)
        item['id'] = str(asset.get('id') or asset.get('asset_id') or '').strip()
        item['kind'] = 'visual'
        item['media_type'] = 'video' if is_video else 'image'
        if _is_asset_id(item['id']):
            out.append(item)
    return out


def _resolve_ffmpeg():
    env_path = str(os.environ.get('FFMPEG_PATH') or '').strip()
    bundled = os.path.join(
        SCRIPTS_DIR, 'bin', 'ffmpeg.exe' if os.name == 'nt' else 'ffmpeg'
    )
    for candidate in (env_path, bundled, 'ffmpeg'):
        if not candidate:
            continue
        if candidate != 'ffmpeg' and not os.path.isfile(candidate):
            continue
        try:
            result = subprocess.run(
                [candidate, '-version'],
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                universal_newlines=True,
            )
            if result.returncode == 0:
                return candidate
        except Exception:
            continue
    return ''


def _resolve_ffprobe(ffmpeg_path=''):
    if ffmpeg_path:
        probe = ffmpeg_path.replace('ffmpeg.exe', 'ffprobe.exe').replace(
            'ffmpeg', 'ffprobe'
        )
        if probe != ffmpeg_path and os.path.isfile(probe):
            return probe
    bundled = os.path.join(
        SCRIPTS_DIR, 'bin', 'ffprobe.exe' if os.name == 'nt' else 'ffprobe'
    )
    for candidate in (bundled, 'ffprobe'):
        if candidate != 'ffprobe' and not os.path.isfile(candidate):
            continue
        try:
            result = subprocess.run(
                [candidate, '-version'],
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                universal_newlines=True,
            )
            if result.returncode == 0:
                return candidate
        except Exception:
            continue
    return ''


def _probe_duration_sec(path):
    """Best-effort media duration in whole seconds."""
    if not path or not os.path.isfile(path):
        return 0
    probe = _resolve_ffprobe(_resolve_ffmpeg())
    if probe:
        try:
            result = subprocess.run(
                [
                    probe, '-v', 'error',
                    '-show_entries', 'format=duration',
                    '-of', 'default=nokey=1:noprint_wrappers=1',
                    path,
                ],
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                universal_newlines=True,
            )
            if result.returncode == 0:
                raw = str(result.stdout or '').strip().splitlines()
                if raw:
                    length = float(raw[0].strip())
                    if length > 0:
                        return max(1, int(round(length)))
        except Exception:
            pass
    try:
        from mutagen import File as MutagenFile
        audio = MutagenFile(path)
        info = getattr(audio, 'info', None) if audio is not None else None
        length = float(getattr(info, 'length', 0) or 0)
        if length > 0:
            return max(1, int(round(length)))
    except Exception:
        pass
    return 0


def _asset_duration_sec(asset):
    """Integer duration seconds from registry display, else probe the master."""
    display = asset.get('display') if isinstance(asset.get('display'), dict) else {}
    try:
        duration = int(display.get('duration') or 0)
    except (TypeError, ValueError):
        duration = 0
    if duration > 0:
        return duration
    return _probe_duration_sec(_master_path(asset))


def _ensure_dedupe_temp_root():
    try:
        if not os.path.isdir(SITE_HEALTH_TEMP_DIR):
            os.makedirs(SITE_HEALTH_TEMP_DIR)
    except Exception:
        return ''
    return SITE_HEALTH_TEMP_DIR


def _demux_stream_fingerprint(src_path, stream_map):
    """
    XXH3 of the full stream-copied elementary stream (tags/container ignored).

    Returns (digest_hex, byte_count). Empty digest on failure.
    Hashes stdout — no temp file; dual ID3/APE artwork cannot change the result.
    """
    ffmpeg = _resolve_ffmpeg()
    if not ffmpeg or xxhash is None or not src_path or not os.path.isfile(src_path):
        return '', 0
    try:
        proc = subprocess.Popen(
            [
                ffmpeg, '-nostdin', '-hide_banner', '-loglevel', 'error',
                '-i', src_path,
                '-map', stream_map,
                '-c', 'copy',
                '-f', 'data',
                '-',
            ],
            stdout=subprocess.PIPE,
            stderr=subprocess.DEVNULL,
        )
    except Exception:
        return '', 0
    hasher = xxhash.xxh3_64()
    total = 0
    stdout = proc.stdout
    if stdout is None:
        try:
            proc.kill()
        except Exception:
            pass
        return '', 0
    try:
        while True:
            chunk = stdout.read(1024 * 1024)
            if not chunk:
                break
            hasher.update(chunk)
            total += len(chunk)
        proc.wait()
    except Exception:
        try:
            proc.kill()
        except Exception:
            pass
        return '', 0
    if total <= 0:
        return '', 0
    digest = hasher.hexdigest().lower()
    if not digest or digest == ('0' * len(digest)):
        return '', 0
    return digest, total


def _cluster_audio_by_stream_hash(members, progress_cb=None, progress_state=None):
    """
    Full-audio path: demux-copy hash every member (no duration pre-bucket).

    Same compressed audio with different ID3/APE/artwork → identical digest.
    True remasters / different encodes → different digest.
    """
    if xxhash is None or not members:
        return []
    by_digest = defaultdict(list)
    for asset in members:
        if progress_state is not None and progress_cb:
            progress_state['current'] = progress_state.get('current', 0) + 1
            current = progress_state['current']
            total = progress_state.get('total') or current
            if current == 1 or current % 5 == 0 or current == total:
                progress_cb(current, total)
        path = _master_path(asset)
        if not path or not os.path.isfile(path):
            continue
        digest, nbytes = _demux_stream_fingerprint(path, '0:a:0')
        if not digest:
            continue
        # Include byte count so a pathological hash collision across sizes is split.
        key = '{0}:{1}'.format(nbytes, digest)
        by_digest[key].append(asset)

    clusters = []
    for digest, group in by_digest.items():
        if len(group) < 2:
            continue
        clusters.append({
            'digest': digest,
            'members': group,
            'kind': 'audio',
        })
    clusters.sort(key=lambda c: (-len(c['members']), c.get('digest') or ''))
    return clusters


def _demux_copy_to_file(src_path, stream_map, dest_path, max_bytes):
    """
    Stream-copy one elementary stream to a raw data file (not for playback).

    max_bytes uses ffmpeg -fs so extracts stay bounded.
    """
    ffmpeg = _resolve_ffmpeg()
    if not ffmpeg or not src_path or not os.path.isfile(src_path):
        return False
    try:
        if os.path.isfile(dest_path):
            os.unlink(dest_path)
    except Exception:
        pass
    cmd = [
        ffmpeg, '-nostdin', '-y', '-hide_banner', '-loglevel', 'error',
        '-i', src_path,
        '-map', stream_map,
        '-c', 'copy',
        '-f', 'data',
        '-fs', str(int(max_bytes)),
        dest_path,
    ]
    try:
        result = subprocess.run(
            cmd,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            universal_newlines=True,
        )
    except Exception:
        return False
    if not os.path.isfile(dest_path):
        return False
    try:
        return os.path.getsize(dest_path) > 0
    except Exception:
        return False


def _xxh3_file_prefix(path, nbytes):
    if xxhash is None or not path or not os.path.isfile(path) or nbytes <= 0:
        return ''
    try:
        hasher = xxhash.xxh3_64()
        remaining = int(nbytes)
        with open(path, 'rb') as handle:
            while remaining > 0:
                chunk = handle.read(min(1024 * 1024, remaining))
                if not chunk:
                    break
                hasher.update(chunk)
                remaining -= len(chunk)
        digest = hasher.hexdigest().lower()
        if not digest or digest == ('0' * len(digest)):
            return ''
        return digest
    except Exception:
        return ''


def _cluster_demux_group(members, stream_map, kind_label, progress_cb=None, progress_state=None):
    """
    Demux-copy each member into temp/, hash the shared prefix
    (shortest demux dump in the group), return digest clusters.
    """
    if xxhash is None or len(members) < 2:
        return []
    root = _ensure_dedupe_temp_root()
    if not root:
        return []
    work = os.path.join(
        root,
        'run-{0}-{1}'.format(os.getpid(), int(time.time() * 1000) % 100000000),
    )
    try:
        os.makedirs(work)
    except Exception:
        return []

    extracted = []
    try:
        for asset in members:
            if progress_state is not None and progress_cb:
                progress_state['current'] = progress_state.get('current', 0) + 1
                current = progress_state['current']
                total = progress_state.get('total') or current
                if current == 1 or current % 5 == 0 or current == total:
                    progress_cb(current, total)
            path = _master_path(asset)
            aid = str(asset.get('id') or '').strip() or 'unknown'
            if not path or not os.path.isfile(path):
                continue
            dest = os.path.join(work, '{0}.data'.format(aid))
            if not _demux_copy_to_file(
                path, stream_map, dest, DEMUX_EXTRACT_MAX_BYTES
            ):
                continue
            extracted.append((asset, dest))

        if len(extracted) < 2:
            return []

        sizes = []
        for _asset, dest in extracted:
            try:
                sizes.append(os.path.getsize(dest))
            except Exception:
                sizes.append(0)
        min_size = min(sizes) if sizes else 0
        if min_size <= 0:
            return []

        by_digest = defaultdict(list)
        for asset, dest in extracted:
            digest = _xxh3_file_prefix(dest, min_size)
            if not digest:
                continue
            # Include shared prefix length so different mins cannot collide across runs.
            key = '{0}:{1}'.format(min_size, digest)
            by_digest[key].append(asset)

        clusters = []
        for digest, group in by_digest.items():
            if len(group) < 2:
                continue
            clusters.append({
                'digest': digest,
                'members': group,
                'kind': kind_label,
            })
        clusters.sort(key=lambda c: (-len(c['members']), c.get('digest') or ''))
        return clusters
    finally:
        try:
            shutil.rmtree(work, ignore_errors=True)
        except Exception:
            pass


def content_fingerprint_image(path):
    """XXH3 of width/height + RGB pixels (ignores EXIF/XMP)."""
    try:
        from PIL import Image
    except ImportError:
        return ''
    if not os.path.isfile(path):
        return ''
    try:
        with Image.open(path) as img:
            rgb = img.convert('RGB')
            width, height = rgb.size
            payload = struct.pack('>II', int(width), int(height)) + rgb.tobytes()
        return _xxh3_bytes(payload)
    except Exception:
        return ''


def _walk_asset_ids(node, found):
    """Collect ast_* strings anywhere in a JSON-like structure."""
    if isinstance(node, dict):
        for key, value in node.items():
            if key in (
                'asset_id', 'poster_asset_id', 'cover_asset_id',
                'living_cover_asset_id', 'background_video_asset_id',
            ) or key.endswith('_asset_id'):
                text = str(value or '').strip()
                if _is_asset_id(text):
                    found.add(text)
            if key in ('asset_ids',) and isinstance(value, dict):
                for slot_val in value.values():
                    text = str(slot_val or '').strip()
                    if _is_asset_id(text):
                        found.add(text)
            if key in ('library_asset_ids', 'press_photo_asset_ids') and isinstance(value, list):
                for item in value:
                    text = str(item or '').strip()
                    if _is_asset_id(text):
                        found.add(text)
            _walk_asset_ids(value, found)
    elif isinstance(node, list):
        for item in node:
            _walk_asset_ids(item, found)
    elif isinstance(node, str):
        text = node.strip()
        if _is_asset_id(text):
            found.add(text)


def build_reference_index(registry=None):
    """
    Map asset_id → list of {kind, path, label} references.

    Campaign-class refs (campaigns/releases/playlists) count as hard keep signals.
    """
    refs = defaultdict(list)

    for label, folder in _CONTAINER_DIRS:
        if not os.path.isdir(folder):
            continue
        try:
            names = os.listdir(folder)
        except Exception:
            continue
        for name in names:
            if not name.endswith('.json') or name == 'registry.json':
                continue
            path = os.path.join(folder, name)
            try:
                with open(path, 'r', encoding='utf-8') as handle:
                    payload = json.load(handle)
            except Exception:
                continue
            found = set()
            _walk_asset_ids(payload, found)
            kind = 'campaign' if label in ('campaigns', 'releases') else label.rstrip('s')
            if label == 'playlists':
                kind = 'playlist'
            elif label == 'brands':
                kind = 'brand'
            elif label == 'galleries':
                kind = 'gallery'
            elif label == 'pages':
                kind = 'page'
            for asset_id in found:
                refs[asset_id].append({
                    'kind': kind,
                    'path': path,
                    'label': '{0}/{1}'.format(label, name),
                })

    # Audio display covers (registry → visual).
    if registry and isinstance(registry.get('assets'), dict):
        for asset_id, asset in registry['assets'].items():
            if not isinstance(asset, dict):
                continue
            if str(asset.get('kind') or '').strip().lower() != 'audio':
                continue
            display = asset.get('display') if isinstance(asset.get('display'), dict) else {}
            for key in ('cover', 'living_cover', 'cover_asset_id', 'living_cover_asset_id'):
                raw = display.get(key)
                text = str(raw or '').strip()
                # cover may be a path; prefer embedded asset id fields
                if key in ('cover', 'living_cover') and not _is_asset_id(text):
                    continue
                if _is_asset_id(text):
                    refs[text].append({
                        'kind': 'track_cover',
                        'path': 'registry:{0}'.format(asset_id),
                        'label': 'audio {0}.{1}'.format(asset_id, key),
                    })
    return refs


def _is_campaign_linked(ref_list):
    for ref in ref_list or []:
        if ref.get('kind') in ('campaign', 'playlist'):
            return True
    return False


def _is_hard_referenced(ref_list):
    """Any container or track-cover ref makes the asset 'in use'."""
    for ref in ref_list or []:
        if ref.get('kind') in (
            'campaign', 'playlist', 'brand', 'gallery', 'page', 'track_cover'
        ):
            return True
    return False


def score_keep(asset, refs_for_id):
    """
    Higher score wins.
    Campaign/playlist refs beat brand/gallery; then oldest created_at; then lower id.
    """
    score = 0
    if _is_campaign_linked(refs_for_id):
        score += 1000
    if _is_hard_referenced(refs_for_id):
        score += 100
    created = str(asset.get('created_at') or '')
    # Prefer older: invert lexicographic timestamp roughly by using negative
    # of a sortable string — we break ties explicitly in choose_keeper.
    return score, created, str(asset.get('id') or '')


def collapse_remaps(remaps):
    """
    Flatten loser→keeper chains and drop cycles / self-maps.

    Example: A→B and B→C becomes A→C, B→C. Never leave a loser whose
    keeper is itself scheduled for deletion without following the chain.
    """
    if not isinstance(remaps, dict) or not remaps:
        return {}

    def resolve(start):
        seen = []
        cur = str(start or '').strip()
        while cur and cur in remaps:
            if cur in seen:
                cycle = seen[seen.index(cur):]
                return min(cycle) if cycle else cur
            seen.append(cur)
            nxt = str(remaps.get(cur) or '').strip()
            if not nxt or nxt == cur:
                break
            cur = nxt
        return cur

    collapsed = {}
    for loser in remaps.keys():
        loser_id = str(loser or '').strip()
        if not loser_id:
            continue
        keeper_id = resolve(loser_id)
        if keeper_id and keeper_id != loser_id:
            collapsed[loser_id] = keeper_id

    # A final keeper must never appear as a loser.
    final_keepers = set(collapsed.values())
    for loser_id in list(collapsed.keys()):
        if loser_id in final_keepers:
            del collapsed[loser_id]
    return collapsed


def choose_keeper(members, ref_index):
    """Return (keeper_asset, remove_list, conflict:bool)."""
    if not members:
        return None, [], False
    scored = []
    campaign_count = 0
    for asset in members:
        aid = str(asset.get('id') or '')
        refs = ref_index.get(aid) or []
        if _is_campaign_linked(refs):
            campaign_count += 1
        scored.append((score_keep(asset, refs), asset, refs))
    # Sort: higher score first; older created_at first; lower asset_id first
    scored.sort(key=lambda row: (-row[0][0], row[0][1] or '9999', row[0][2]))
    keeper = scored[0][1]
    if campaign_count >= 2:
        return keeper, [], True
    remove = [row[1] for row in scored[1:]]
    # Only propose removal of members that are NOT hard-referenced alone when
    # keeper already covers campaign need — still allow remove of unreferenced.
    safe_remove = []
    for asset in remove:
        aid = str(asset.get('id') or '')
        refs = ref_index.get(aid) or []
        if _is_campaign_linked(refs):
            # Should not happen when campaign_count < 2, but be safe.
            return keeper, [], True
        safe_remove.append(asset)
    return keeper, safe_remove, False


def _cluster_by_digest(assets, digest_fn, progress_cb=None, progress_every=25):
    """
    digest_fn(asset) -> hex string.
    Returns list of {digest, members, kind}.
    """
    buckets = defaultdict(list)
    total = len(assets)
    every = max(1, int(progress_every or 25))
    for index, asset in enumerate(assets, start=1):
        digest = digest_fn(asset)
        if progress_cb and (index == 1 or index % every == 0 or index == total):
            progress_cb(index, total)
        if not digest:
            continue
        buckets[digest].append(asset)
    clusters = []
    for digest, members in buckets.items():
        if len(members) < 2:
            continue
        kinds = set(str(m.get('kind') or '') for m in members)
        # Do not mix audio and visual in one cluster even if hash collided.
        if len(kinds) > 1:
            by_kind = defaultdict(list)
            for member in members:
                by_kind[str(member.get('kind') or '')].append(member)
            for kind, group in by_kind.items():
                if len(group) >= 2:
                    clusters.append({
                        'digest': digest,
                        'members': group,
                        'kind': kind,
                    })
            continue
        clusters.append({
            'digest': digest,
            'members': members,
            'kind': next(iter(kinds)) if kinds else '',
        })
    clusters.sort(key=lambda c: (-len(c['members']), c.get('digest') or ''))
    return clusters


def xxhash_available():
    return xxhash is not None


def find_file_hash_clusters(registry, progress_cb=None):
    """
    Quick path: group by file size, hash only within multi-member size buckets.
    """
    if xxhash is None:
        return []
    assets = _candidate_assets(registry)
    by_size = defaultdict(list)
    for asset in assets:
        path = _master_path(asset)
        if not path or not os.path.isfile(path):
            continue
        try:
            size = os.path.getsize(path)
        except Exception:
            continue
        if size <= 0:
            continue
        by_size[size].append(asset)

    to_hash = []
    multi_buckets = 0
    for size, group in by_size.items():
        if len(group) >= 2:
            multi_buckets += 1
            to_hash.extend(group)

    # Stash scan stats for callers / Activity (attribute on function).
    find_file_hash_clusters.last_stats = {
        'candidates': len(assets),
        'size_buckets': len(by_size),
        'multi_size_buckets': multi_buckets,
        'hashed': len(to_hash),
    }

    if not to_hash:
        return []

    try:
        import log as health_log
        health_log.info(
            'Same-size buckets ready: {0} multi-member bucket(s), '
            'hashing {1} of {2} candidate(s)...'.format(
                multi_buckets, len(to_hash), len(assets)
            )
        )
    except Exception:
        pass

    def digest_fn(asset):
        return _file_xxh3(_master_path(asset))

    return _cluster_by_digest(
        to_hash, digest_fn, progress_cb=progress_cb, progress_every=25
    )


def find_content_hash_clusters(registry, progress_cb=None):
    """
    Full path: content fingerprints ignoring tags/metadata.

    Audio: demux-copy hash of the full audio elementary stream for every
    candidate (no duration pre-bucket — dual ID3/APE artwork must not hide clones).
    Video: same integer duration → demux-copy to temp/, hash min shared bytes.
    Stills: same pixel width×height → RGB hash.
    """
    stats = {
        'candidates': 0,
        'audio_fingerprinted': 0,
        'audio_multi_stream_groups': 0,
        'video_skipped_no_duration': 0,
        'video_multi_duration_groups': 0,
        'still_multi_dim_groups': 0,
        'demuxed': 0,
        'stills_hashed': 0,
        'xxhash': xxhash is not None,
    }
    find_content_hash_clusters.last_stats = stats

    if xxhash is None:
        return []
    assets = _candidate_assets(registry)
    stats['candidates'] = len(assets)
    audio_assets = []
    video_by_duration = defaultdict(list)
    still_by_dims = defaultdict(list)
    for asset in assets:
        kind = str(asset.get('kind') or '').strip().lower()
        media_type = str(asset.get('media_type') or '').strip().lower()
        if kind == 'audio':
            audio_assets.append(asset)
            continue
        if kind == 'visual' and media_type == 'video':
            duration = _asset_duration_sec(asset)
            if duration <= 0:
                stats['video_skipped_no_duration'] += 1
                continue
            video_by_duration[duration].append(asset)
            continue
        if kind == 'visual':
            path = _master_path(asset)
            if not path or not os.path.isfile(path):
                continue
            try:
                from PIL import Image
                with Image.open(path) as img:
                    dims = (int(img.size[0]), int(img.size[1]))
            except Exception:
                continue
            if dims[0] <= 0 or dims[1] <= 0:
                continue
            still_by_dims[dims].append(asset)

    video_groups = [g for g in video_by_duration.values() if len(g) >= 2]
    still_groups = [g for g in still_by_dims.values() if len(g) >= 2]
    stats['video_multi_duration_groups'] = len(video_groups)
    stats['still_multi_dim_groups'] = len(still_groups)

    demux_total = len(audio_assets) + sum(len(g) for g in video_groups)
    still_total = sum(len(g) for g in still_groups)
    progress_state = {'current': 0, 'total': max(1, demux_total + still_total)}

    try:
        import log as health_log
        health_log.info(
            'Content buckets ready: {0} audio (full demux-hash, no duration gate), '
            '{1} video duration-group(s), {2} still dim-group(s); '
            'fingerprinting up to {3} master(s)...'.format(
                len(audio_assets),
                len(video_groups),
                len(still_groups),
                demux_total + still_total,
            )
        )
    except Exception:
        pass

    clusters = []
    if audio_assets:
        before = progress_state['current']
        audio_clusters = _cluster_audio_by_stream_hash(
            audio_assets,
            progress_cb=progress_cb,
            progress_state=progress_state,
        )
        clusters.extend(audio_clusters)
        stats['audio_fingerprinted'] = max(0, progress_state['current'] - before)
        stats['audio_multi_stream_groups'] = len(audio_clusters)
        stats['demuxed'] += stats['audio_fingerprinted']

    for group in video_groups:
        before = progress_state['current']
        clusters.extend(
            _cluster_demux_group(
                group, '0:v:0', 'video',
                progress_cb=progress_cb,
                progress_state=progress_state,
            )
        )
        stats['demuxed'] += max(0, progress_state['current'] - before)

    still_to_hash = []
    for group in still_groups:
        still_to_hash.extend(group)
    stats['stills_hashed'] = len(still_to_hash)
    if still_to_hash:
        def _still_digest(asset):
            if progress_cb and progress_state is not None:
                progress_state['current'] = progress_state.get('current', 0) + 1
                current = progress_state['current']
                total = progress_state.get('total') or current
                if current % 10 == 0 or current == total:
                    progress_cb(current, total)
            return content_fingerprint_image(_master_path(asset))

        clusters.extend(
            _cluster_by_digest(still_to_hash, _still_digest, progress_cb=None)
        )

    clusters.sort(key=lambda c: (-len(c.get('members') or []), c.get('digest') or ''))
    return clusters


def annotate_clusters(clusters, ref_index=None):
    """
    Attach keeper / remove / conflict to each cluster.
    Returns (safe_clusters, conflict_clusters).
    """
    if ref_index is None:
        ref_index = build_reference_index()
    safe = []
    conflict = []
    for cluster in clusters:
        members = cluster.get('members') or []
        keeper, remove, is_conflict = choose_keeper(members, ref_index)
        if keeper is None:
            continue
        row = {
            'digest': cluster.get('digest') or '',
            'kind': cluster.get('kind') or '',
            'members': members,
            'keeper_id': str(keeper.get('id') or ''),
            'remove_ids': [str(a.get('id') or '') for a in remove],
            'member_ids': [str(a.get('id') or '') for a in members],
            'conflict': bool(is_conflict),
        }
        if is_conflict or not row['remove_ids']:
            row['conflict'] = True
            conflict.append(row)
        else:
            safe.append(row)
    return safe, conflict


def cluster_sample_lines(clusters, limit=12):
    """Short operator-facing sample strings for findings UI."""
    lines = []
    for cluster in clusters[:limit]:
        keeper = cluster.get('keeper_id') or '?'
        removes = cluster.get('remove_ids') or []
        kind = cluster.get('kind') or 'asset'
        if cluster.get('conflict'):
            lines.append(
                '{0} conflict keep={1} members={2}'.format(
                    kind, keeper, ','.join(cluster.get('member_ids') or [])[:80]
                )
            )
        else:
            lines.append(
                '{0} keep={1} remove={2}'.format(
                    kind, keeper, ','.join(removes[:4])
                )
            )
    return lines


def replace_asset_id_in_obj(node, old_id, new_id):
    """In-place rewrite of asset id strings in a JSON structure. Returns change count."""
    changed = 0
    old_id = str(old_id or '').strip()
    new_id = str(new_id or '').strip()
    if not old_id or not new_id or old_id == new_id:
        return 0
    if isinstance(node, dict):
        for key in list(node.keys()):
            value = node[key]
            if isinstance(value, str) and value.strip() == old_id:
                node[key] = new_id
                changed += 1
            elif isinstance(value, (dict, list)):
                changed += replace_asset_id_in_obj(value, old_id, new_id)
    elif isinstance(node, list):
        for index, value in enumerate(node):
            if isinstance(value, str) and value.strip() == old_id:
                node[index] = new_id
                changed += 1
            elif isinstance(value, (dict, list)):
                changed += replace_asset_id_in_obj(value, old_id, new_id)
    return changed


def retarget_containers(old_id, new_id):
    """Rewrite container JSON files. Returns number of files changed."""
    files_changed = 0
    for _label, folder in _CONTAINER_DIRS:
        if not os.path.isdir(folder):
            continue
        try:
            names = os.listdir(folder)
        except Exception:
            continue
        for name in names:
            if not name.endswith('.json'):
                continue
            path = os.path.join(folder, name)
            try:
                with open(path, 'r', encoding='utf-8') as handle:
                    payload = json.load(handle)
            except Exception:
                continue
            changed = replace_asset_id_in_obj(payload, old_id, new_id)
            if not changed:
                continue
            tmp = path + '.tmp'
            try:
                with open(tmp, 'w', encoding='utf-8', newline='\n') as handle:
                    json.dump(payload, handle, ensure_ascii=False, indent=2)
                    handle.write('\n')
                if os.path.isfile(path):
                    os.replace(tmp, path)
                else:
                    os.rename(tmp, path)
                files_changed += 1
            except Exception:
                try:
                    if os.path.isfile(tmp):
                        os.unlink(tmp)
                except Exception:
                    pass
    return files_changed


def retarget_registry_covers(registry, old_id, new_id):
    """Rewrite audio display cover asset ids on the registry in memory."""
    changed = 0
    assets = registry.get('assets') if isinstance(registry, dict) else {}
    if not isinstance(assets, dict):
        return 0
    for _aid, asset in assets.items():
        if not isinstance(asset, dict):
            continue
        display = asset.get('display')
        if not isinstance(display, dict):
            continue
        for key in ('cover', 'living_cover', 'cover_asset_id', 'living_cover_asset_id'):
            if str(display.get(key) or '').strip() == old_id:
                display[key] = new_id
                changed += 1
    return changed


def unregister_and_delete_master(registry, asset_id):
    """
    Remove registry row + master file + delivery dir for asset_id.
    Returns True if registry row was removed.
    """
    asset_id = str(asset_id or '').strip()
    assets = registry.get('assets') if isinstance(registry, dict) else {}
    if not isinstance(assets, dict) or asset_id not in assets:
        return False
    asset = assets.get(asset_id) or {}
    master = os.path.basename(str(asset.get('master_filename') or '').strip())
    original = os.path.basename(str(asset.get('original_filename') or '').strip())
    kind = str(asset.get('kind') or '').strip().lower()

    del assets[asset_id]
    by_master = registry.get('by_master_filename')
    if isinstance(by_master, dict):
        for key in list(by_master.keys()):
            if str(by_master.get(key) or '').strip() == asset_id:
                del by_master[key]
        if master and master in by_master:
            del by_master[master]
    by_original = registry.get('by_original_filename')
    if isinstance(by_original, dict):
        for key in list(by_original.keys()):
            if str(by_original.get(key) or '').strip() == asset_id:
                del by_original[key]
        if original and original in by_original:
            del by_original[original]

    # Disk cleanup (best-effort).
    if kind == 'audio' and master:
        path = os.path.join(AUDIO_MASTER_DIR, master)
        try:
            if os.path.isfile(path):
                os.unlink(path)
        except Exception:
            pass
        stem = os.path.splitext(master)[0]
        opt = os.path.join(
            os.path.join(ROOT_DIR, 'media', 'audio', 'optimal'),
            stem + '.mp3',
        )
        try:
            if os.path.isfile(opt):
                os.unlink(opt)
        except Exception:
            pass
    elif kind == 'visual' and master:
        path = os.path.join(VISUAL_MASTER_DIR, master)
        try:
            if os.path.isfile(path):
                os.unlink(path)
        except Exception:
            pass
        delivery = os.path.join(VISUAL_DELIVERY_DIR, asset_id)
        if os.path.isdir(delivery):
            try:
                import shutil
                shutil.rmtree(delivery)
            except Exception:
                pass
    return True
