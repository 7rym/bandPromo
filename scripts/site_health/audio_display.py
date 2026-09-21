# -*- coding: utf-8 -*-
"""
Fill registry audio display from master embedded tags (registry ← master only).

Never writes tags onto masters. Empty registry rows from a bare register-in-place
are a bad import; Treat heals them by reading mutagen tags into display.*.
"""

from __future__ import print_function

import os
import re
import sys

import log
import registry as reg
from paths import ROOT_DIR, SCRIPTS_DIR

try:
    from stopflag import stop_requested
except Exception:
    def stop_requested():
        return False

if SCRIPTS_DIR not in sys.path:
    sys.path.insert(0, SCRIPTS_DIR)

_ASSET_ID_RE = re.compile(r'^ast_[0-9A-HJKMNP-TV-Z]{20}$', re.IGNORECASE)


def _safe_text(value):
    return str(value or '').strip()


def _split_title_version(raw_title):
    """Light port of PHP bandpromo_campaign_resolve_track_display_labels title/version."""
    title = ' '.join(_safe_text(raw_title).replace('\r', ' ').replace('\n', ' ').split())
    version = ''
    if title.endswith(']') and '[' in title:
        open_idx = title.rfind('[')
        if open_idx > 0:
            version = title[open_idx + 1:-1].strip()
            title = title[:open_idx].rstrip()
    return title, version


def display_from_inspect(inspect, preserve=None):
    """Build a registry display dict from audioMasterMetadata inspect output."""
    preserve = preserve if isinstance(preserve, dict) else {}
    raw_title = _safe_text(inspect.get('title'))
    title, version = _split_title_version(raw_title)
    artist = _safe_text(inspect.get('artist'))
    album = _safe_text(inspect.get('album'))

    existing_cover = _safe_text(preserve.get('cover'))
    sidecar = os.path.basename(_safe_text(inspect.get('sidecar_cover') or inspect.get('cover')))
    cover = existing_cover or sidecar

    existing_version = _safe_text(preserve.get('version'))
    if not version and existing_version:
        version = existing_version

    return {
        'title': title,
        'version': version,
        'artist': artist,
        'featured_artist': _safe_text(inspect.get('featured_artist') or preserve.get('featured_artist')),
        'remix_artist': _safe_text(inspect.get('remix_artist') or preserve.get('remix_artist')),
        'album': album,
        'duration': max(0, int(inspect.get('duration_seconds') or inspect.get('duration') or 0)),
        'bitrate_kbps': max(0, int(inspect.get('bitrate_kbps') or 0)),
        'sample_rate_hz': max(0, int(inspect.get('sample_rate_hz') or 0)),
        'bit_depth': max(0, int(inspect.get('bit_depth') or 0)),
        'date': _safe_text(inspect.get('date')),
        'tracknumber': _safe_text(inspect.get('tracknumber')),
        'bpm': _safe_text(inspect.get('bpm')),
        'initialkey': _safe_text(inspect.get('initialkey')),
        'genre': _safe_text(inspect.get('genre')),
        'comment': _safe_text(inspect.get('comment')),
        'lyrics': str(inspect.get('lyrics') or ''),
        'text_role': _safe_text(preserve.get('text_role')) or 'lyrics',
        'notes_label': _safe_text(preserve.get('notes_label')),
        'cover': cover,
        'living_cover': _safe_text(inspect.get('living_cover') or preserve.get('living_cover')),
    }


def display_needs_tag_fill(entry):
    """True when registry display looks like a bare register (no real title/artist)."""
    if not isinstance(entry, dict) or str(entry.get('kind') or '') != 'audio':
        return False
    display = entry.get('display') if isinstance(entry.get('display'), dict) else {}
    title = _safe_text(display.get('title'))
    artist = _safe_text(display.get('artist'))
    if title == '' or title.lower() == 'untitled':
        return True
    if artist == '' and (display.get('duration') in (None, '', 0) or int(display.get('duration') or 0) <= 0):
        # Title alone without duration often means invent/placeholder, not a tag read.
        if _ASSET_ID_RE.match(title):
            return True
    return False


def inspect_master_file(master_filename):
    master_filename = os.path.basename(str(master_filename or '').strip())
    if not master_filename:
        return None
    path = os.path.join(ROOT_DIR, 'media', 'audio', 'master', master_filename)
    if not os.path.isfile(path):
        return None
    try:
        import audioMasterMetadata as amm
    except Exception:
        return None
    try_fn = getattr(amm, 'try_inspect_master', None)
    if try_fn is None:
        return None
    from pathlib import Path
    return try_fn(Path(path))


def apply_inspect_to_entry(entry, inspect):
    """
    Fill empty-ish display from inspect. Never clears a non-empty operator title
    with a weaker invent; never writes onto the master file.
    """
    if not isinstance(entry, dict) or not isinstance(inspect, dict):
        return False
    preserve = entry.get('display') if isinstance(entry.get('display'), dict) else {}
    built = display_from_inspect(inspect, preserve)
    if not built.get('title') and not built.get('artist'):
        # Tags truly empty — leave registry as-is (Files will still show Untitled).
        if int(built.get('duration') or 0) > 0 and int(preserve.get('duration') or 0) <= 0:
            preserve = dict(preserve)
            preserve['duration'] = built['duration']
            if built.get('bitrate_kbps'):
                preserve['bitrate_kbps'] = built['bitrate_kbps']
            if built.get('sample_rate_hz'):
                preserve['sample_rate_hz'] = built['sample_rate_hz']
            if built.get('bit_depth'):
                preserve['bit_depth'] = built['bit_depth']
            entry['display'] = preserve
            return True
        return False

    existing_title = _safe_text(preserve.get('title'))
    if existing_title and existing_title.lower() != 'untitled' and not _ASSET_ID_RE.match(existing_title):
        # Keep operator title; still fill missing artist/duration/lyrics when empty.
        for key in (
            'artist', 'featured_artist', 'remix_artist', 'album', 'duration',
            'bitrate_kbps', 'sample_rate_hz', 'bit_depth',
            'date', 'tracknumber', 'bpm', 'initialkey', 'genre', 'comment', 'lyrics',
            'cover', 'living_cover', 'version',
        ):
            if key == 'lyrics':
                if not str(preserve.get('lyrics') or '').strip() and str(built.get('lyrics') or '').strip():
                    preserve['lyrics'] = built['lyrics']
                continue
            if key == 'duration':
                if int(preserve.get('duration') or 0) <= 0 and int(built.get('duration') or 0) > 0:
                    preserve['duration'] = built['duration']
                continue
            if not _safe_text(preserve.get(key)) and _safe_text(built.get(key)):
                preserve[key] = built[key]
        entry['display'] = preserve
        return True

    entry['display'] = built
    return True


def fill_entry_from_master_tags(entry):
    master = os.path.basename(str(entry.get('master_filename') or '').strip())
    inspect = inspect_master_file(master)
    if inspect is None:
        return False, 'inspect_failed'
    changed = apply_inspect_to_entry(entry, inspect)
    return changed, 'ok' if changed else 'unchanged'


def incomplete_audio_masters(registry):
    """Registered audio assets whose display still looks like a bare import."""
    audio, _v, _s, _o = reg.assets_by_kind(registry)
    pending = []
    for entry in audio:
        if not display_needs_tag_fill(entry):
            continue
        master = os.path.basename(str(entry.get('master_filename') or '').strip())
        if not master:
            continue
        path = os.path.join(ROOT_DIR, 'media', 'audio', 'master', master)
        if not os.path.isfile(path):
            continue
        pending.append({
            'asset_id': str(entry.get('id') or ''),
            'master_filename': master,
        })
    return pending


def treat_audio_fill_display_from_tags(registry=None):
    """
    Heal bare register rows: read master tags into registry display.
    Direction is always registry ← master (safe). Does not touch master tags.
    """
    log.phase('treat:audio_display')
    owns_load = registry is None
    if owns_load:
        registry, status = reg.load_registry()
        if status not in ('ok', 'missing'):
            log.info('Cannot fill audio display: registry {0}'.format(status))
            log.treat_result('audio_fill_display_from_tags', 'failed', 0)
            return 0, 1
        if status == 'missing':
            registry = reg.empty_registry()

    pending = incomplete_audio_masters(registry)
    total = len(pending)
    if total == 0:
        log.info('No audio registry rows need tag fill.')
        log.treat_result('audio_fill_display_from_tags', 'ok', 0)
        return 0, 0

    log.info('Filling registry display from master tags for {0} track(s)...'.format(total))
    fixed = 0
    failed = 0
    for index, item in enumerate(pending, 1):
        if stop_requested():
            log.info('Stop requested during audio display fill.')
            break
        asset_id = item.get('asset_id') or ''
        name = item.get('master_filename') or ''
        log.progress('audio_fill_display_from_tags', index, total, name)
        entry = registry.get('assets', {}).get(asset_id)
        if not isinstance(entry, dict):
            failed += 1
            log.info('Missing registry row for {0}'.format(asset_id or name))
            continue
        try:
            changed, status = fill_entry_from_master_tags(entry)
            if status == 'inspect_failed':
                failed += 1
                log.info('Could not read tags from {0}'.format(name))
                continue
            if changed:
                registry['assets'][asset_id] = entry
                title = _safe_text((entry.get('display') or {}).get('title')) or '(no title tag)'
                artist = _safe_text((entry.get('display') or {}).get('artist'))
                label = '{0} — {1}'.format(artist, title) if artist else title
                log.info('Filled {0}: {1}'.format(name, label))
                fixed += 1
        except Exception as exc:
            failed += 1
            log.info('Failed display fill {0}: {1}'.format(name, exc))

    if owns_load:
        try:
            reg.write_registry(registry)
        except Exception as exc:
            log.info('Could not write registry after display fill: {0}'.format(exc))
            log.treat_result('audio_fill_display_from_tags', 'failed', fixed)
            return fixed, failed + 1

    log.info('Filled display for {0} track(s); {1} failed.'.format(fixed, failed))
    log.treat_result(
        'audio_fill_display_from_tags',
        'ok' if failed == 0 else 'partial',
        fixed,
    )
    return fixed, failed
