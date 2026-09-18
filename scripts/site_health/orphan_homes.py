# -*- coding: utf-8 -*-
"""
Orphan catalogue homes used in playlists / galleries / pages.

When an audio or visual asset has an empty (or invisible primary) home but is
referenced by a container owned by exactly one campaign, Site health can stamp
that home. Never overwrites a non-empty foreign home.
"""

from __future__ import print_function

import json
import os
import re

import log
import registry as reg
from paths import ROOT_DIR

try:
    from stopflag import stop_requested
except Exception:
    def stop_requested():
        return False

_ASSET_ID_RE = re.compile(r'^ast_[A-Za-z0-9]+$', re.IGNORECASE)
_PRIMARY_HOME = 'primary'

_CONTAINER_DIRS = (
    ('playlist', os.path.join(ROOT_DIR, 'data', 'playlists')),
    ('gallery', os.path.join(ROOT_DIR, 'data', 'galleries')),
    ('page', os.path.join(ROOT_DIR, 'data', 'pages')),
)


def _is_asset_id(value):
    text = str(value or '').strip()
    return bool(text) and _ASSET_ID_RE.match(text) is not None


def _walk_asset_ids(node, found):
    if isinstance(node, dict):
        for key, value in node.items():
            key_l = str(key or '').lower()
            if key_l in ('asset_id', 'poster_asset_id', 'cover_asset_id', 'living_cover_asset_id'):
                if _is_asset_id(value):
                    found.add(str(value).strip())
            elif key_l in ('src', 'poster', 'cover', 'living_cover'):
                text = str(value or '').strip()
                if _is_asset_id(text):
                    found.add(text)
                elif text.startswith('ast_'):
                    # ast_XXX.ext path stem
                    stem = text.split('/')[-1].split('\\')[-1]
                    if '.' in stem:
                        stem = stem.rsplit('.', 1)[0]
                    if _is_asset_id(stem):
                        found.add(stem)
            else:
                _walk_asset_ids(value, found)
    elif isinstance(node, list):
        for item in node:
            _walk_asset_ids(item, found)


def _document_campaign_id(payload):
    if not isinstance(payload, dict):
        return ''
    for key in ('campaign_id', 'release_id'):
        raw = str(payload.get(key) or '').strip()
        if raw and raw.lower() != _PRIMARY_HOME:
            return raw
    return ''


def _home_is_orphan(release_id):
    home = str(release_id or '').strip()
    return home == '' or home.lower() == _PRIMARY_HOME


def probe_orphan_homes(registry=None):
    """
    Return {
      'stampable': [{asset_id, kind, campaign_id, containers: [...]}],
      'ambiguous': [{asset_id, kind, campaign_ids: [...], containers: [...]}],
    }
    """
    if registry is None:
        registry = reg.load_registry()
    assets = registry.get('assets') if isinstance(registry, dict) else None
    if not isinstance(assets, dict):
        return {'stampable': [], 'ambiguous': []}

    # asset_id → set of campaign ids from container ownership
    owners = {}
    # asset_id → list of container labels
    container_labels = {}

    for kind, folder in _CONTAINER_DIRS:
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
            campaign_id = _document_campaign_id(payload)
            if campaign_id == '':
                continue
            found = set()
            _walk_asset_ids(payload, found)
            label = '{0}/{1}'.format(kind, name)
            for asset_id in found:
                owners.setdefault(asset_id, set()).add(campaign_id)
                container_labels.setdefault(asset_id, []).append(label)

    stampable = []
    ambiguous = []
    for asset_id, campaign_ids in owners.items():
        asset = assets.get(asset_id)
        if not isinstance(asset, dict):
            continue
        kind = str(asset.get('kind') or '').strip().lower()
        if kind not in ('audio', 'visual'):
            continue
        if not _home_is_orphan(asset.get('release_id')):
            continue
        campaigns = sorted(campaign_ids)
        labels = container_labels.get(asset_id) or []
        row = {
            'asset_id': asset_id,
            'kind': kind,
            'containers': labels,
        }
        if len(campaigns) == 1:
            row['campaign_id'] = campaigns[0]
            stampable.append(row)
        else:
            row['campaign_ids'] = campaigns
            ambiguous.append(row)

    stampable.sort(key=lambda r: r.get('asset_id') or '')
    ambiguous.sort(key=lambda r: r.get('asset_id') or '')
    return {'stampable': stampable, 'ambiguous': ambiguous}


def treat_orphan_homes():
    """Stamp unambiguous orphan homes. Returns (fixed, failed)."""
    log.phase('treat:orphan_homes')
    registry = reg.load_registry()
    if not isinstance(registry, dict) or not isinstance(registry.get('assets'), dict):
        log.info('Registry unavailable — cannot stamp catalogue homes.')
        log.treat_result('orphan_home_stamp', 'failed', 0)
        return 0, 1

    probe = probe_orphan_homes(registry)
    stampable = probe.get('stampable') or []
    ambiguous = probe.get('ambiguous') or []
    if ambiguous:
        log.items(
            'Orphans used by multiple campaigns (left alone)',
            [r.get('asset_id') for r in ambiguous],
        )
    if not stampable:
        log.info('No unambiguous orphan-in-container homes to stamp.')
        log.treat_result('orphan_home_stamp', 'ok', 0)
        return 0, 0

    log.items(
        'Stamping catalogue homes',
        ['{0} → {1}'.format(r.get('asset_id'), r.get('campaign_id')) for r in stampable],
    )

    fixed = 0
    failed = 0
    assets = registry['assets']
    dirty = False
    for row in stampable:
        if stop_requested():
            log.info('Stop requested during orphan home stamp.')
            break
        asset_id = str(row.get('asset_id') or '').strip()
        campaign_id = str(row.get('campaign_id') or '').strip()
        asset = assets.get(asset_id)
        if not isinstance(asset, dict) or campaign_id == '':
            failed += 1
            continue
        if not _home_is_orphan(asset.get('release_id')):
            continue
        asset['release_id'] = campaign_id
        assets[asset_id] = asset
        dirty = True
        fixed += 1
        # Audio also belongs on the campaign tracks pool when missing.
        if str(asset.get('kind') or '').lower() == 'audio':
            _ensure_audio_on_campaign_tracks(asset_id, campaign_id)

    if dirty:
        try:
            reg.write_registry(registry)
        except Exception as exc:
            log.info('Failed to write registry after home stamp: {0}'.format(exc))
            log.treat_result('orphan_home_stamp', 'failed', fixed)
            return fixed, max(failed, 1)

    log.treat_result(
        'orphan_home_stamp',
        'ok' if failed == 0 else 'partial',
        fixed,
    )
    return fixed, failed


def _ensure_audio_on_campaign_tracks(asset_id, campaign_id):
    """Append asset_id to campaign tracks[] when not already listed."""
    path = os.path.join(ROOT_DIR, 'data', 'campaigns', campaign_id + '.json')
    if not os.path.isfile(path):
        return
    try:
        with open(path, 'r', encoding='utf-8') as handle:
            doc = json.load(handle)
    except Exception:
        return
    if not isinstance(doc, dict):
        return
    tracks = doc.get('tracks')
    if not isinstance(tracks, list):
        tracks = []
    for track in tracks:
        if isinstance(track, dict) and str(track.get('asset_id') or '').strip() == asset_id:
            return
    tracks.append({'asset_id': asset_id})
    doc['tracks'] = tracks
    tmp = path + '.tmp'
    try:
        with open(tmp, 'w', encoding='utf-8', newline='\n') as handle:
            json.dump(doc, handle, ensure_ascii=False, indent=2)
            handle.write('\n')
        if os.path.isfile(path):
            os.replace(tmp, path)
        else:
            os.rename(tmp, path)
    except Exception as exc:
        log.info('Could not append {0} to campaign {1}: {2}'.format(
            asset_id, campaign_id, exc
        ))
        try:
            if os.path.isfile(tmp):
                os.remove(tmp)
        except Exception:
            pass
