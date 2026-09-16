#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Read-only publish diagnostics for dry-run builds.

Reports registry vs disk mismatch so operators know why Files → Audio can be
empty while media/audio/master still holds hundreds of masters.
"""

from __future__ import print_function

import json
import os
import sys

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
ROOT_DIR = os.path.dirname(SCRIPT_DIR)
REGISTRY_PATH = os.path.join(ROOT_DIR, 'data', 'assets', 'registry.json')
AUDIO_MASTER_DIR = os.path.join(ROOT_DIR, 'media', 'audio', 'master')
AUDIO_ORIGINAL_DIR = os.path.join(ROOT_DIR, 'media', 'audio', 'original')
VISUAL_MASTER_DIR = os.path.join(ROOT_DIR, 'media', 'visual', 'master')

AUDIO_EXTS = ('.flac', '.mp3', '.wav')
VISUAL_EXTS = ('.jpg', '.jpeg', '.png', '.webp', '.gif', '.mkv', '.mp4', '.webm')


def _norm(value):
    return str(value or '').strip()


def _is_ast_stem(filename):
    stem = os.path.splitext(os.path.basename(filename))[0]
    return stem.upper().startswith('AST_') and len(stem) >= 8


def _list_files(directory, allowed_exts):
    if not os.path.isdir(directory):
        return []
    found = []
    try:
        names = os.listdir(directory)
    except Exception:
        return []
    for name in names:
        if name in ('.', '..') or name.lower() == 'desktop.ini':
            continue
        path = os.path.join(directory, name)
        if not os.path.isfile(path):
            continue
        ext = os.path.splitext(name)[1].lower()
        if ext not in allowed_exts:
            continue
        found.append(name)
    found.sort()
    return found


def _first_non_empty(asset, keys):
    for key in keys:
        val = _norm(asset.get(key))
        if val:
            return val
    return ''


def _hash_key(asset):
    for key in (
        'sha256',
        'master_sha256',
        'source_sha256',
        'file_sha256',
        'content_hash',
        'content_sha256',
        'content_xxh3',
    ):
        value = _norm(asset.get(key))
        if value:
            return value.lower()
    return ''


def _audio_label(asset):
    display = asset.get('display') if isinstance(asset.get('display'), dict) else {}
    title = _first_non_empty(display, ('title', 'track_title', 'name'))
    if not title:
        title = _first_non_empty(asset, ('display_title', 'title', 'track_title', 'name'))
    artist = _first_non_empty(display, ('artist', 'track_artist'))
    if not artist:
        artist = _first_non_empty(asset, ('display_artist', 'artist', 'track_artist'))
    if title and artist:
        return '{0} by {1}'.format(title, artist)
    if title:
        return title
    return _norm(asset.get('asset_id')) or '(untitled)'


def _visual_label(asset):
    display = asset.get('display') if isinstance(asset.get('display'), dict) else {}
    title = _first_non_empty(display, ('title', 'name'))
    if not title:
        title = _first_non_empty(asset, ('title', 'display_title', 'name', 'asset_id'))
    return title or '(untitled visual)'


def _cluster(assets, label_fn):
    by_label = {}
    by_hash = {}
    for item in assets:
        label = label_fn(item)
        by_label.setdefault(label, []).append(item)
        h = _hash_key(item)
        if h:
            by_hash.setdefault(h, []).append(item)
    return by_label, by_hash


def _print_top_clusters(title, clusters, limit=8):
    noisy = [(name, members) for name, members in clusters.items() if len(members) > 1]
    noisy.sort(key=lambda pair: len(pair[1]), reverse=True)
    print(title + ': {0} cluster(s) with duplicates'.format(len(noisy)))
    shown = 0
    for name, members in noisy:
        print('  - {0} ({1})'.format(name, len(members)))
        for member in members[:4]:
            asset_id = _norm(member.get('asset_id'))
            master = _norm(member.get('master_filename'))
            print('      · {0} [{1}]'.format(asset_id or '(no id)', master or 'no master file'))
        if len(members) > 4:
            print('      · ... {0} more'.format(len(members) - 4))
        shown += 1
        if shown >= limit:
            break
    if not noisy:
        print('  - none')


def _load_registry():
    if not os.path.isfile(REGISTRY_PATH):
        return None, 'Registry missing: {0}'.format(REGISTRY_PATH)
    try:
        with open(REGISTRY_PATH, 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
    except Exception as exc:
        return None, 'Could not read registry: {0}'.format(exc)
    assets_map = payload.get('assets') if isinstance(payload, dict) else None
    if not isinstance(assets_map, dict):
        return None, 'Registry format is invalid (assets map missing).'
    assets = []
    for asset_id, raw in assets_map.items():
        if not isinstance(raw, dict):
            continue
        asset = dict(raw)
        asset.setdefault('asset_id', asset_id)
        assets.append(asset)
    return assets, ''


def main():
    print('Dry-run diagnostics stage (read-only)')
    print('Root: {0}'.format(ROOT_DIR))
    print('')

    assets, err = _load_registry()
    if assets is None:
        print('FAILED {0}'.format(err))
        return 1

    audio_assets = []
    visual_assets = []
    sfx_assets = []
    other_assets = []
    for asset in assets:
        kind = _norm(asset.get('kind')).lower()
        if kind in ('', 'audio'):
            audio_assets.append(asset)
        elif kind == 'visual':
            visual_assets.append(asset)
        elif kind == 'sfx':
            sfx_assets.append(asset)
        else:
            other_assets.append(asset)

    disk_audio_masters = _list_files(AUDIO_MASTER_DIR, AUDIO_EXTS)
    disk_audio_ast = [name for name in disk_audio_masters if _is_ast_stem(name)]
    disk_audio_originals = _list_files(AUDIO_ORIGINAL_DIR, AUDIO_EXTS)
    disk_visual_masters = _list_files(VISUAL_MASTER_DIR, VISUAL_EXTS)
    disk_visual_ast = [name for name in disk_visual_masters if _is_ast_stem(name)]

    registry_audio_masters = set()
    for asset in audio_assets:
        master = os.path.basename(_norm(asset.get('master_filename')))
        if master:
            registry_audio_masters.add(master)

    registry_visual_masters = set()
    for asset in visual_assets:
        master = os.path.basename(_norm(asset.get('master_filename')))
        if master:
            registry_visual_masters.add(master)

    uncatalogued_audio = [name for name in disk_audio_ast if name not in registry_audio_masters]
    uncatalogued_visual = [name for name in disk_visual_ast if name not in registry_visual_masters]

    print('=== Inventory (disk vs registry) ===')
    print('Registry assets: {0} total ({1} audio, {2} visual, {3} sfx, {4} other)'.format(
        len(assets), len(audio_assets), len(visual_assets), len(sfx_assets), len(other_assets)
    ))
    print('Disk audio masters: {0} (ast_* {1})'.format(len(disk_audio_masters), len(disk_audio_ast)))
    print('Disk audio originals: {0}'.format(len(disk_audio_originals)))
    print('Disk visual masters: {0} (ast_* {1})'.format(len(disk_visual_masters), len(disk_visual_ast)))
    print('Uncatalogued audio masters (on disk, missing from registry): {0}'.format(len(uncatalogued_audio)))
    print('Uncatalogued visual masters (on disk, missing from registry): {0}'.format(len(uncatalogued_visual)))
    print('')

    critical = False
    if len(uncatalogued_audio) > 0 and len(audio_assets) == 0:
        critical = True
        print('CRITICAL: Files → Audio is empty because the registry has 0 audio assets,')
        print('          while {0} audio master(s) still exist on disk.'.format(len(uncatalogued_audio)))
        print('          Earlier Refresh runs rebuilt delivery copies from orphans without')
        print('          leaving a stable Files catalogue — that wasted time and disk.')
        print('')
        print('NEXT STEP (safe, no full rebuild):')
        print('  1. System → Status → Site health → Quick check → Review → Apply')
        print('  2. Preview, then Apply — this registers existing masters in place')
        print('     (no new copies; no 40-minute media rebuild).')
        print('  3. Confirm Files → Audio lists your tracks.')
        print('  4. Only then run Refresh site files to rebuild listener deliverables.')
        print('')
        print('Sample uncatalogued audio masters:')
        for name in uncatalogued_audio[:12]:
            print('  - {0}'.format(name))
        if len(uncatalogued_audio) > 12:
            print('  - ... {0} more'.format(len(uncatalogued_audio) - 12))
        print('')
    elif len(uncatalogued_audio) > 0:
        print('WARNING: {0} audio master(s) on disk are not in the registry.'.format(len(uncatalogued_audio)))
        print('         Run Site health Apply to register them in place.')
        print('')

    if len(uncatalogued_visual) > 0 and len(visual_assets) == 0:
        critical = True
        print('CRITICAL: Visual registry is empty while {0} visual master(s) exist on disk.'.format(
            len(uncatalogued_visual)
        ))
        print('          Same recovery: Site health Apply (register in place).')
        print('')

    audio_by_label, audio_by_hash = _cluster(audio_assets, _audio_label)
    visual_by_label, visual_by_hash = _cluster(visual_assets, _visual_label)

    print('=== Duplicate clusters (registry only) ===')
    _print_top_clusters('Audio duplicate titles', audio_by_label)
    _print_top_clusters('Audio duplicate hashes', audio_by_hash)
    _print_top_clusters('Visual duplicate titles', visual_by_label)
    _print_top_clusters('Visual duplicate hashes', visual_by_hash)
    print('')

    print('BUILD_STATS {"catalog":{"handled":%d,"created":0,"fresh":%d,"failed":0}}' % (
        len(assets), len(assets)
    ))
    if critical:
        print('DRY_RUN_RESULT:needs_repair')
        print('Dry-run diagnostics finished. No files were modified.')
        print('Do not Refresh yet — register disk masters via Repair Apply first.')
    else:
        print('DRY_RUN_RESULT:ok')
        print('Dry-run diagnostics finished. No files were modified.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
