# -*- coding: utf-8 -*-
"""Cheap triage checks for site health."""

from __future__ import print_function

import hashlib
import json
import os
import platform
import shutil
import sys

import log
import plan as plan_mod
import registry as reg
from paths import (
    FINGERPRINT_CACHE_PATH,
    JSON_FINGERPRINT_PATHS,
    ROOT_DIR,
)


def _file_sha256(path):
    if not os.path.isfile(path):
        return ''
    hasher = hashlib.sha256()
    try:
        with open(path, 'rb') as handle:
            while True:
                chunk = handle.read(1024 * 256)
                if not chunk:
                    break
                hasher.update(chunk)
    except Exception:
        return ''
    return hasher.hexdigest()


def host_fingerprint(app_version):
    parts = [
        platform.system(),
        platform.release(),
        platform.machine(),
        sys.version.split()[0],
        app_version or '',
        ROOT_DIR,
    ]
    raw = '|'.join(parts)
    return hashlib.sha256(raw.encode('utf-8', 'replace')).hexdigest()[:16]


def ffmpeg_available():
    bundled = os.path.join(ROOT_DIR, 'scripts', 'bin', 'ffmpeg')
    if os.name == 'nt':
        bundled += '.exe'
    if os.path.isfile(bundled):
        return bundled
    return shutil.which('ffmpeg') or ''


def load_fingerprint_cache():
    if not os.path.isfile(FINGERPRINT_CACHE_PATH):
        return {}
    try:
        with open(FINGERPRINT_CACHE_PATH, 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
        return payload if isinstance(payload, dict) else {}
    except Exception:
        return {}


def save_fingerprint_cache(cache):
    folder = os.path.dirname(FINGERPRINT_CACHE_PATH)
    if not os.path.isdir(folder):
        os.makedirs(folder)
    tmp = FINGERPRINT_CACHE_PATH + '.tmp'
    with open(tmp, 'w', encoding='utf-8', newline='\n') as handle:
        json.dump(cache, handle, ensure_ascii=False, indent=2)
        handle.write('\n')
    if os.path.isfile(FINGERPRINT_CACHE_PATH):
        os.replace(tmp, FINGERPRINT_CACHE_PATH)
    else:
        os.rename(tmp, FINGERPRINT_CACHE_PATH)


def json_fingerprints():
    result = {}
    for key, path in JSON_FINGERPRINT_PATHS:
        if not os.path.isfile(path):
            result[key] = {'path': path, 'exists': False, 'sha256': ''}
            continue
        result[key] = {
            'path': path,
            'exists': True,
            'sha256': _file_sha256(path),
            'size': os.path.getsize(path),
        }
    return result


def run_triage(plan, deep=False, suppress_json_drift=False):
    log.phase('triage')
    deep = bool(deep)
    app_version = plan_mod.read_app_version()
    plan['app_version'] = app_version
    fp = host_fingerprint(app_version)
    plan['host_fingerprint'] = fp
    cache = load_fingerprint_cache()
    cache_hit = (
        not deep
        and str(cache.get('host_fingerprint') or '') == fp
        and str(cache.get('app_version') or '') == app_version
    )
    if deep:
        log.info('Host fingerprint: {0} (full check — cache ignored)'.format(fp))
        # Bust trust: do not compare against prior JSON digests.
        previous = {}
        cache = {}
    else:
        log.info('Host fingerprint: {0} ({1})'.format(
            fp, 'cache hit' if cache_hit else 'full environment check'
        ))
        previous = (
            cache.get('json_fingerprints')
            if isinstance(cache.get('json_fingerprints'), dict)
            else {}
        )

    py_ok = sys.version_info >= (3, 6)
    ffmpeg = ffmpeg_available()
    log.info('Python: {0}'.format(sys.version.split()[0]))
    if not py_ok:
        plan_mod.add_finding(
            plan, 'python_too_old', 'critical',
            'Python is too old for the health engine', 1,
            '', body='Need CPython 3.6.9 or newer on this host.'
        )
    if ffmpeg:
        log.info('ffmpeg: {0}'.format(ffmpeg))
    else:
        plan_mod.add_finding(
            plan, 'ffmpeg_missing', 'attention',
            'ffmpeg not found', 1,
            '',
            body='Player-ready file builds need ffmpeg. Open System → Environment to locate or install it.',
        )

    registry, status = reg.load_registry()
    if status == 'missing':
        plan_mod.add_finding(
            plan, 'registry_missing', 'critical',
            'The Files catalogue index is missing', 1,
            'audio_register_in_place',
            body='Site health cannot see which songs and visuals belong in Files.',
        )
        log.info('Registry: missing')
        disk_audio_ast = [
            n for n in reg.list_audio_masters_on_disk()
            if reg.is_asset_id(os.path.splitext(n)[0])
        ]
        if disk_audio_ast:
            plan_mod.add_finding(
                plan, 'uncatalogued_audio_masters', 'critical',
                'Songs on disk are not in Files yet', len(disk_audio_ast),
                'audio_register_in_place',
                sample=disk_audio_ast,
                body=(
                    '{0} song file(s) are on the server but not listed in Files yet.'
                ).format(len(disk_audio_ast)),
            )
    elif status != 'ok':
        plan_mod.add_finding(
            plan, 'registry_unreadable', 'critical',
            'The Files catalogue index could not be read', 1,
            '',
            body=status,
        )
        log.info('Registry: {0}'.format(status))
    else:
        audio, visual, sfx, other = reg.assets_by_kind(registry)
        log.info('Registry: {0} audio, {1} visual, {2} sfx'.format(
            len(audio), len(visual), len(sfx)
        ))
        if other:
            # Unknown kind — not audio/visual/sfx (empty kind counts as audio).
            log.info(
                'Registry: {0} asset(s) with unknown kind (not audio/visual/sfx)'.format(
                    len(other)
                )
            )

        disk_audio = reg.list_audio_masters_on_disk()
        disk_audio_ast = [
            n for n in disk_audio if reg.is_asset_id(os.path.splitext(n)[0])
        ]
        disk_audio_other = [
            n for n in disk_audio if not reg.is_asset_id(os.path.splitext(n)[0])
        ]
        disk_originals = reg.list_audio_originals_on_disk()
        disk_visual = reg.list_visual_masters_on_disk()
        disk_sfx = reg.list_sfx_masters_on_disk()
        disk_sfx_ast = [
            n for n in disk_sfx if reg.is_asset_id(os.path.splitext(n)[0])
        ]
        log.info('Disk audio masters: {0} (ast_* {1}), originals: {2}'.format(
            len(disk_audio), len(disk_audio_ast), len(disk_originals)
        ))
        log.info('Disk visual masters: {0}'.format(len(disk_visual)))
        log.info('Disk sound-effect masters: {0} (ast_* {1})'.format(
            len(disk_sfx), len(disk_sfx_ast)
        ))
        if deep and disk_audio_other:
            log.info('Non-ast_* audio masters on disk: {0}'.format(len(disk_audio_other)))
            plan_mod.add_finding(
                plan, 'non_ast_audio_masters', 'attention',
                'Some audio files are not named in the usual way', len(disk_audio_other),
                '',
                sample=disk_audio_other[:12],
                body=(
                    '{0} song file(s) on the server use an unexpected name, so Site health '
                    'cannot add them to Files automatically — see Activity for the list.'
                ).format(len(disk_audio_other)),
            )

        pending_audio = reg.uncatalogued_audio_masters(registry)
        pending_visual = reg.uncatalogued_visual_masters(registry)
        pending_sfx = reg.uncatalogued_sfx_masters(registry)

        # Hard safety net: never call an empty Files catalogue "healthy" when
        # masters exist on disk (covers id-format drift and non-ast_* names).
        if len(audio) == 0 and len(disk_audio) > 0 and not pending_audio:
            plan_mod.add_finding(
                plan, 'empty_audio_registry_with_disk_masters', 'critical',
                'Songs on disk are not in Files yet', len(disk_audio),
                'audio_register_in_place',
                sample=disk_audio[:12],
                body=(
                    '{0} song file(s) are on the server but Files → Audio shows none. '
                    'Add them into Files so the catalogue matches the disk.'
                ).format(len(disk_audio)),
            )

        if pending_audio:
            severity = 'critical' if len(audio) == 0 else 'attention'
            plan_mod.add_finding(
                plan, 'uncatalogued_audio_masters', severity,
                'Songs on disk are not in Files yet', len(pending_audio),
                'audio_register_in_place',
                sample=[p['master_filename'] for p in pending_audio],
                body=(
                    '{0} song file(s) are on the server but not listed in Files yet. '
                    'Files → Audio will look empty until they are added.'
                ).format(len(pending_audio)),
            )

        # Bare register (id only, no tag fill) is a bad import — Files shows Untitled.
        if not pending_audio and len(audio) > 0:
            try:
                import audio_display
                bare = audio_display.incomplete_audio_masters(registry)
            except Exception as exc:
                log.info('Audio display completeness probe skipped: {0}'.format(exc))
                bare = []
            if bare:
                plan_mod.add_finding(
                    plan, 'audio_display_missing_tags', 'attention',
                    'Some tracks are in Files without tag data', len(bare),
                    'audio_fill_display_from_tags',
                    sample=[p.get('master_filename') for p in bare],
                    body=(
                        '{0} track(s) in Files have no useful title or artist yet. '
                        'Apply can fill those from the file\'s own tags (the original file is not rewritten).'
                    ).format(len(bare)),
                )

        # Embedded artwork present but display.cover empty — extract to Visual.
        if len(audio) > 0:
            try:
                import audio_covers
                cover_pending = audio_covers.pending_cover_extract(registry)
            except Exception as exc:
                log.info('Audio cover extract probe skipped: {0}'.format(exc))
                cover_pending = []
            if cover_pending:
                plan_mod.add_finding(
                    plan, 'audio_embedded_cover_unextracted', 'attention',
                    'Some tracks have embedded art but no cover in Files',
                    len(cover_pending),
                    'audio_extract_covers',
                    sample=[p.get('master_filename') for p in cover_pending],
                    body=(
                        '{0} track(s) already include cover art inside the audio file, '
                        'but Files has no cover linked yet. Apply can extract and attach it.'
                    ).format(len(cover_pending)),
                )

        if pending_visual:
            plan_mod.add_finding(
                plan, 'uncatalogued_visual_masters', 'attention',
                'Pictures or videos on disk are not in Files yet', len(pending_visual),
                'visual_register_in_place',
                sample=[p['master_filename'] for p in pending_visual],
                body=(
                    '{0} picture or video file(s) are on the server but not listed in Files yet.'
                ).format(len(pending_visual)),
            )

        if len(sfx) == 0 and len(disk_sfx) > 0 and not pending_sfx:
            plan_mod.add_finding(
                plan, 'empty_sfx_registry_with_disk_masters', 'attention',
                'Sound effects on disk are not in Files yet', len(disk_sfx),
                'sfx_register_in_place',
                sample=disk_sfx[:12],
                body=(
                    '{0} sound-effect file(s) are on the server but Files → Sound effects shows none.'
                ).format(len(disk_sfx)),
            )

        if pending_sfx:
            plan_mod.add_finding(
                plan, 'uncatalogued_sfx_masters', 'attention',
                'Sound effects on disk are not in Files yet', len(pending_sfx),
                'sfx_register_in_place',
                sample=[p['master_filename'] for p in pending_sfx],
                body=(
                    '{0} sound-effect file(s) are on the server but not listed in Files yet.'
                ).format(len(pending_sfx)),
            )

        # Cheap delivery existence checks (no checksums).
        # Always probe registered assets — independent of register-pending findings.
        # (Orphan delivery folders after wipe/re-register must still be scored.)
        missing_audio = reg.missing_audio_deliverables(registry)
        if missing_audio:
            plan_mod.add_finding(
                plan, 'missing_audio_delivery', 'attention',
                'Some tracks are not ready to stream yet', len(missing_audio),
                'listener_delivery',
                sample=[m.get('asset_id') or m.get('master_filename') for m in missing_audio],
                body=(
                    '{0} track(s) in Files still need a player-ready streaming file built.'
                ).format(len(missing_audio)),
            )
        missing_visual = reg.missing_visual_deliveries(registry)
        if missing_visual:
            plan_mod.add_finding(
                plan, 'missing_visual_delivery', 'attention',
                'Some pictures or videos are not player-ready yet', len(missing_visual),
                'listener_delivery',
                sample=[m.get('asset_id') for m in missing_visual],
                body=(
                    '{0} visual item(s) in Files still need player-ready artwork or video files built.'
                ).format(len(missing_visual)),
            )
        missing_sfx = reg.missing_sfx_deliverables(registry)
        if missing_sfx:
            plan_mod.add_finding(
                plan, 'missing_sfx_delivery', 'attention',
                'Some sound effects are not play-ready yet', len(missing_sfx),
                'sfx_delivery',
                sample=[m.get('asset_id') or m.get('master_filename') for m in missing_sfx],
                body=(
                    '{0} sound effect(s) in Files still need a player-ready play file built.'
                ).format(len(missing_sfx)),
            )

        # Homeless derived/legacy/leftover intake/junk under media/ (icons stay ignored).
        try:
            import janitor
            janitor_targets = janitor.probe_janitor_targets(registry)
        except Exception as exc:
            log.info('Media janitor probe skipped: {0}'.format(exc))
            janitor_targets = []
        if janitor_targets:
            plan_mod.add_finding(
                plan, 'media_janitor_orphans', 'attention',
                'Unused files and folders can be cleaned up', len(janitor_targets),
                'media_janitor_prune',
                sample=[t.get('path') for t in janitor_targets],
                body=(
                    '{0} leftover file(s) or empty folder(s) that are safe to clear — '
                    'including orphan intake uploads and stray ZIP files under media/. '
                    'Icons stay put. Master files are never deleted here.'
                ).format(len(janitor_targets)),
            )

        # data/ ephemeral junk + orphan / unlinked containers.
        try:
            import data_janitor
            data_probe = data_janitor.probe_all()
        except Exception as exc:
            log.info('Data janitor probe skipped: {0}'.format(exc))
            data_probe = {
                'ephemeral': [],
                'containers': {'relinkable': [], 'registry_stubs': [], 'manual': []},
            }
        ephemeral = data_probe.get('ephemeral') or []
        containers = data_probe.get('containers') or {}
        relinkable = containers.get('relinkable') or []
        stubs = containers.get('registry_stubs') or []
        manual_containers = containers.get('manual') or []
        if ephemeral:
            plan_mod.add_finding(
                plan, 'data_janitor_ephemeral', 'attention',
                'Leftover temporary site-data files can be cleaned up', len(ephemeral),
                'data_janitor_prune',
                sample=[t.get('path') for t in ephemeral],
                body=(
                    '{0} leftover temporary file(s) or empty folder(s) that are safe to clear. '
                    'Your catalogue documents, analytics, and install settings stay put.'
                ).format(len(ephemeral)),
            )

        # Stuck Site update / package export scratch + older Ready Jobs archives.
        try:
            import storage_reclaim
            package_scratch = storage_reclaim.probe_package_scratch()
            archives_probe = storage_reclaim.probe_ready_archives()
        except Exception as exc:
            log.info('Storage reclaim probe skipped: {0}'.format(exc))
            package_scratch = []
            archives_probe = {'removable': [], 'kept': []}
        if package_scratch:
            pkg_bytes = storage_reclaim.total_size_bytes(package_scratch)
            plan_mod.add_finding(
                plan, 'storage_package_scratch', 'attention',
                'Leftover package folders can free disk space', len(package_scratch),
                'storage_package_prune',
                sample=[t.get('path') for t in package_scratch],
                body=(
                    '{0} leftover Site update or export folder(s) ({1}) that are safe to clear. '
                    'Your catalogue and media library stay put.'
                ).format(len(package_scratch), storage_reclaim.format_bytes(pkg_bytes)),
            )
        removable_archives = archives_probe.get('removable') or []
        if removable_archives:
            arc_bytes = storage_reclaim.total_size_bytes(removable_archives)
            kept = archives_probe.get('kept') or []
            kept_note = ''
            if kept:
                kept_note = ' Keeps the newest Ready Backup, PCF, and PBF on the server.'
            plan_mod.add_finding(
                plan, 'storage_ready_archives', 'attention',
                'Older Ready archives can free disk space', len(removable_archives),
                'storage_archives_prune',
                sample=[
                    '{0}/{1}'.format(r.get('kind_label'), r.get('job_id'))
                    for r in removable_archives
                ],
                body=(
                    '{0} older Ready archive(s) in Jobs ({1}) can be removed.'
                    '{2}'
                ).format(
                    len(removable_archives),
                    storage_reclaim.format_bytes(arc_bytes),
                    kept_note,
                ),
            )

        relink_count = len(relinkable) + len(stubs)
        if relink_count:
            sample = [
                '{0}/{1} → {2}'.format(
                    r.get('kind'), r.get('id'), r.get('campaign_id') or '(drop stub)',
                )
                for r in (relinkable + stubs)
            ]
            plan_mod.add_finding(
                plan, 'data_container_unlinked', 'attention',
                'Catalogue items need reconnecting', relink_count,
                'data_container_relink',
                sample=sample,
                body=(
                    '{0} playlist, gallery, or page item(s) are invisible or half-linked. '
                    'Apply reconnects ones that already belong to a campaign, and clears empty stubs.'
                ).format(relink_count),
            )
        if manual_containers:
            sample_rows = []
            for row in manual_containers:
                formatted = data_janitor.format_manual_sample_row(row)
                if formatted:
                    sample_rows.append(formatted)
            plan_mod.add_finding(
                plan, 'data_container_orphans', 'attention',
                'Catalogue containers need Adopt or Delete', len(manual_containers),
                '',
                sample=sample_rows,
                body=(
                    '{0} playlist/gallery/page item(s) are orphaned or unowned. '
                    'Adopt them into a campaign, or Delete leftovers — Site health will not guess.'
                ).format(len(manual_containers)),
            )

        # Orphans used in playlists / galleries / pages → stamp catalogue home.
        try:
            import orphan_homes
            orphan_probe = orphan_homes.probe_orphan_homes(registry)
        except Exception as exc:
            log.info('Orphan home probe skipped: {0}'.format(exc))
            orphan_probe = {'stampable': [], 'ambiguous': []}
        stampable = orphan_probe.get('stampable') or []
        ambiguous = orphan_probe.get('ambiguous') or []
        if stampable:
            plan_mod.add_finding(
                plan, 'orphan_assets_in_containers', 'attention',
                'Orphan media used in campaigns needs a catalogue home', len(stampable),
                'orphan_home_stamp',
                sample=[
                    '{0} → {1}'.format(
                        r.get('filename') or r.get('asset_id'),
                        r.get('campaign_id'),
                    )
                    for r in stampable
                ],
                body=(
                    '{0} audio/visual file(s) are used in a playlist, gallery, or page '
                    'but have no campaign home. Apply stamps home to that campaign.'
                ).format(len(stampable)),
            )
        if ambiguous:
            sample_rows = []
            for row in ambiguous:
                formatted = orphan_homes.format_ambiguous_sample_row(row)
                if formatted:
                    sample_rows.append(formatted)
            plan_mod.add_finding(
                plan, 'orphan_assets_multi_campaign', 'attention',
                'Orphan media used by more than one campaign', len(ambiguous),
                '',
                sample=sample_rows,
                body=(
                    '{0} file(s) are used by more than one campaign and have no catalogue home. '
                    'Choose which campaign should own each file below — Site health will not guess.'
                ).format(len(ambiguous)),
            )

        # Quick duplicate masters: same size → whole-file XXH3.
        try:
            import dedupe
            if not dedupe.xxhash_available():
                log.info(
                    'Duplicate master probe unavailable: xxhash not importable '
                    '(scripts/vendor bootstrap missing?).'
                )
                plan_mod.add_finding(
                    plan, 'dedupe_unavailable', 'attention',
                    'Duplicate master check could not run', 1,
                    '',
                    body=(
                        'A helper library Site health needs for duplicate checks is missing on this host. '
                        'Streaming may still work. Ask a developer to repair the scripts vendor bundle.'
                    ),
                )
            else:
                log.info('Scanning for duplicate masters (same size + file hash)...')

                def _file_hash_progress(current, total):
                    log.progress(
                        'dedupe_file_hash',
                        current,
                        total,
                        'hashing same-size masters',
                    )
                    log.info(
                        'Hashing same-size masters: {0}/{1}'.format(current, total)
                    )
                    try:
                        from job_heartbeat import touch_heartbeat
                        from paths import META_NAME, ROOT_DIR
                        touch_heartbeat(
                            ROOT_DIR,
                            stage='check',
                            message='Hashing masters {0}/{1}'.format(current, total),
                            name=META_NAME,
                        )
                    except Exception:
                        pass

                file_clusters = dedupe.find_file_hash_clusters(
                    registry, progress_cb=_file_hash_progress
                )
                stats = getattr(dedupe.find_file_hash_clusters, 'last_stats', {}) or {}
                log.info(
                    'File-hash scan: {0} candidates, {1} size bucket(s), '
                    '{2} multi-member bucket(s), hashed {3} file(s).'.format(
                        stats.get('candidates', 0),
                        stats.get('size_buckets', 0),
                        stats.get('multi_size_buckets', 0),
                        stats.get('hashed', 0),
                    )
                )
                ref_index = dedupe.build_reference_index(registry)
                safe_file, conflict_file = dedupe.annotate_clusters(file_clusters, ref_index)
                remove_count = sum(len(c.get('remove_ids') or []) for c in safe_file)
                if safe_file:
                    plan_mod.add_finding(
                        plan, 'duplicate_masters_file', 'attention',
                        'Duplicate masters with identical file bytes', remove_count,
                        'dedupe_retarget_and_remove',
                        sample=dedupe.cluster_sample_lines(safe_file),
                        body=(
                            '{0} group(s) of files look identical on disk. '
                            'Apply keeps the campaign-linked copy and removes {1} unused clone(s).'
                        ).format(len(safe_file), remove_count),
                    )
                    log.items(
                        'Duplicate file-hash clusters (safe to remove)',
                        dedupe.cluster_sample_lines(safe_file, limit=20),
                    )
                if conflict_file:
                    plan_mod.add_finding(
                        plan, 'duplicate_masters_conflict', 'attention',
                        'Duplicate masters both linked to campaigns', len(conflict_file),
                        '',
                        sample=dedupe.cluster_sample_lines(conflict_file),
                        body=(
                            '{0} cluster(s) look identical on disk but more than one member '
                            'is linked to a campaign or playlist — not auto-removed.'
                        ).format(len(conflict_file)),
                    )
                    log.items(
                        'Duplicate file-hash conflicts (manual)',
                        dedupe.cluster_sample_lines(conflict_file, limit=20),
                    )
                if not safe_file and not conflict_file:
                    if int(stats.get('multi_size_buckets') or 0) == 0:
                        log.info('No same-size duplicate master files found.')
                    else:
                        log.info(
                            'Hashed {0} same-size candidate(s); no identical file hashes.'
                            .format(stats.get('hashed', 0))
                        )
        except Exception as exc:
            log.info('Duplicate file-hash probe skipped: {0}'.format(exc))

        if deep and len(audio) > 0:
            try:
                import files_index
                indexed = files_index.count_target_rows('audio')
            except Exception as exc:
                log.info('Files index probe skipped: {0}'.format(exc))
                indexed = -1
            if indexed >= 0 and indexed < len(audio):
                plan_mod.add_finding(
                    plan, 'files_index_audio_undercount', 'attention',
                    'Files → Audio list is behind the catalogue', len(audio) - indexed,
                    'files_index_rebuild',
                    sample=[],
                    body=(
                        'Files → Audio shows {0} row(s) but the catalogue lists {1}. '
                        'Apply can refresh the list without changing your masters.'
                    ).format(indexed, len(audio)),
                )
                log.info('Files index audio rows: {0} (registry {1})'.format(indexed, len(audio)))

    current_fps = json_fingerprints()
    changed = []
    for key, meta in current_fps.items():
        prev = previous.get(key) if isinstance(previous.get(key), dict) else {}
        if not meta.get('exists'):
            continue
        if prev.get('sha256') and prev.get('sha256') != meta.get('sha256'):
            changed.append(key)
    if deep:
        log.info('JSON fingerprints recomputed ({0} keys) — baseline rebuilt.'.format(
            len(current_fps)
        ))
    elif suppress_json_drift:
        if changed:
            log.info(
                'JSON changed during Treat (expected) — refreshing baseline: {0}'.format(
                    ', '.join(changed)
                )
            )
        else:
            log.info('JSON fingerprints unchanged.')
    elif changed and previous:
        plan_mod.add_finding(
            plan, 'json_changed', 'attention',
            'Site data files changed since last health check', len(changed),
            'container_links',
            sample=changed,
            body='Changed: {0}'.format(', '.join(changed)),
        )
        log.info('JSON changed since last check: {0}'.format(', '.join(changed)))
    elif not previous:
        log.info('JSON fingerprint cache cold — storing baseline (no change finding).')
    else:
        log.info('JSON fingerprints unchanged.')

    cache.update({
        'host_fingerprint': fp,
        'app_version': app_version,
        'json_fingerprints': current_fps,
        'last_check_deep': bool(deep),
    })
    save_fingerprint_cache(cache)
    return plan
