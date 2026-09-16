#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Read-only publish diagnostics for dry-run builds.

This script does not mutate runtime files. It reports likely duplication clusters
so operators can inspect before any Repair/Apply run.
"""

from __future__ import print_function

import json
import os
import sys

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
ROOT_DIR = os.path.dirname(SCRIPT_DIR)
REGISTRY_PATH = os.path.join(ROOT_DIR, 'data', 'assets', 'registry.json')


def _norm(value):
    return str(value or '').strip()


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
    ):
        value = _norm(asset.get(key))
        if value:
            return value.lower()
    return ''


def _audio_label(asset):
    title = _first_non_empty(asset, ('display_title', 'title', 'track_title', 'name'))
    artist = _first_non_empty(asset, ('display_artist', 'artist', 'track_artist'))
    if title and artist:
        return '{0} by {1}'.format(title, artist)
    if title:
        return title
    return _norm(asset.get('asset_id')) or '(untitled)'


def _visual_label(asset):
    return _first_non_empty(asset, ('title', 'display_title', 'name', 'asset_id')) or '(untitled visual)'


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


def main():
    print('Dry-run diagnostics stage (read-only)')
    print('Root: {0}'.format(ROOT_DIR))
    if not os.path.isfile(REGISTRY_PATH):
        print('FAILED Registry missing: {0}'.format(REGISTRY_PATH))
        return 1

    with open(REGISTRY_PATH, 'r', encoding='utf-8') as handle:
        payload = json.load(handle)
    assets_map = payload.get('assets') if isinstance(payload, dict) else None
    if not isinstance(assets_map, dict):
        print('FAILED Registry format is invalid (assets map missing).')
        return 1

    assets = []
    for asset_id, raw in assets_map.items():
        if not isinstance(raw, dict):
            continue
        asset = dict(raw)
        asset.setdefault('asset_id', asset_id)
        assets.append(asset)

    audio_assets = [a for a in assets if _norm(a.get('kind')).lower() in ('', 'audio')]
    visual_assets = [a for a in assets if _norm(a.get('kind')).lower() == 'visual']

    print('Registry assets: {0} total ({1} audio, {2} visual)'.format(
        len(assets), len(audio_assets), len(visual_assets)
    ))

    audio_by_label, audio_by_hash = _cluster(audio_assets, _audio_label)
    visual_by_label, visual_by_hash = _cluster(visual_assets, _visual_label)

    _print_top_clusters('Audio duplicate titles', audio_by_label)
    _print_top_clusters('Audio duplicate hashes', audio_by_hash)
    _print_top_clusters('Visual duplicate titles', visual_by_label)
    _print_top_clusters('Visual duplicate hashes', visual_by_hash)

    print('BUILD_STATS {"catalog":{"handled":%d,"created":0,"fresh":%d,"failed":0}}' % (len(assets), len(assets)))
    print('Dry-run diagnostics finished. No files were modified.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
