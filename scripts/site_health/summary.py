# -*- coding: utf-8 -*-
"""
Site health summary — The good, The bad, and The ugly.

Good: registered masters present on disk (ok/total) + container docs present.
Bad: findings that need treatment (missing, unregistered, duplicates, drift…).
Ugly: janitor junk / homeless leftovers under media/ and ephemeral clutter under data/.
"""

from __future__ import print_function

import json
import os

import log
import registry as reg
from paths import (
    AUDIO_MASTER_DIR,
    ROOT_DIR,
    SFX_MASTER_DIR,
    VISUAL_MASTER_DIR,
)

# Findings that are clutter / cleanup, not catalogue repair.
UGLY_FINDING_IDS = frozenset({
    'media_janitor_orphans',
    'data_janitor_ephemeral',
})

# Container registries: (dir under data/, registry list key, operator label).
# Campaigns live in data/campaigns/ (list key still "releases" in the JSON).
_CONTAINER_SPECS = (
    ('campaigns', 'releases', 'Campaigns'),
    ('playlists', 'playlists', 'Playlists'),
    ('galleries', 'galleries', 'Galleries'),
    ('pages', 'pages', 'Pages'),
    ('brands', 'brands', 'Brands'),
)


def _master_dir_for_kind(kind):
    if kind == 'audio':
        return AUDIO_MASTER_DIR
    if kind == 'visual':
        return VISUAL_MASTER_DIR
    if kind == 'sfx':
        return SFX_MASTER_DIR
    return ''


def _registered_master_ratio(assets, kind):
    """Return (ok, total) for registry assets of kind whose master file exists."""
    folder = _master_dir_for_kind(kind)
    ok = 0
    total = 0
    for asset in assets or []:
        if not isinstance(asset, dict):
            continue
        total += 1
        master = os.path.basename(str(asset.get('master_filename') or '').strip())
        if not master or not folder:
            continue
        if os.path.isfile(os.path.join(folder, master)):
            ok += 1
    return ok, total


def _load_container_registry(dirname):
    path = os.path.join(ROOT_DIR, 'data', dirname, 'registry.json')
    if not os.path.isfile(path):
        return None, 'missing'
    try:
        with open(path, 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
        if isinstance(payload, dict):
            return payload, 'ok'
    except Exception as exc:
        return None, str(exc)
    return None, 'unreadable'


def _container_doc_ratio(dirname, list_key):
    """
    Registry entries whose document JSON exists on disk.
    Returns (ok, total, status) where status is ok|missing|unreadable.
    """
    payload, status = _load_container_registry(dirname)
    if status != 'ok' or not isinstance(payload, dict):
        return 0, 0, status
    items = payload.get(list_key)
    if isinstance(items, dict):
        entries = []
        for key, meta in items.items():
            entry = dict(meta) if isinstance(meta, dict) else {}
            entry.setdefault('id', key)
            entries.append(entry)
    elif isinstance(items, list):
        entries = [e for e in items if isinstance(e, dict)]
    else:
        entries = []
    total = len(entries)
    ok = 0
    folder = os.path.join(ROOT_DIR, 'data', dirname)
    for entry in entries:
        entry_id = str(entry.get('id') or '').strip()
        if not entry_id:
            continue
        doc = os.path.join(folder, entry_id + '.json')
        if os.path.isfile(doc):
            ok += 1
    return ok, total, 'ok'


def _good_rows(registry, registry_status):
    rows = []
    if registry_status == 'ok' and isinstance(registry, dict):
        audio, visual, sfx, _other = reg.assets_by_kind(registry)
        for kind, label, assets in (
            ('audio', 'Audio masters', audio),
            ('visual', 'Visual masters', visual),
            ('sfx', 'Sound effects', sfx),
        ):
            ok, total = _registered_master_ratio(assets, kind)
            rows.append({
                'id': kind + '_masters',
                'label': label,
                'ok': int(ok),
                'total': int(total),
            })
    else:
        for kind, label in (
            ('audio', 'Audio masters'),
            ('visual', 'Visual masters'),
            ('sfx', 'Sound effects'),
        ):
            rows.append({
                'id': kind + '_masters',
                'label': label,
                'ok': 0,
                'total': 0,
            })

    for dirname, list_key, label in _CONTAINER_SPECS:
        ok, total, status = _container_doc_ratio(dirname, list_key)
        row = {
            'id': dirname,
            'label': label,
            'ok': int(ok),
            'total': int(total),
        }
        if status != 'ok':
            row['note'] = 'catalogue {0}'.format(status)
        rows.append(row)
    return rows


def _finding_row(finding):
    return {
        'id': str(finding.get('id') or ''),
        'title': str(finding.get('title') or finding.get('id') or 'Finding'),
        'body': str(finding.get('body') or ''),
        'count': int(finding.get('count') or 0),
        'severity': str(finding.get('severity') or 'attention'),
        'treatment': str(finding.get('treatment') or ''),
    }


def build_summary(plan, registry=None, registry_status=None):
    """
    Attach plan['summary'] = { good, bad, ugly } and log a brief Activity line.
    """
    if registry is None or registry_status is None:
        registry, registry_status = reg.load_registry()
        if registry_status == 'missing':
            registry = reg.empty_registry()

    findings = plan.get('findings') if isinstance(plan.get('findings'), list) else []
    bad = []
    ugly = []
    for finding in findings:
        if not isinstance(finding, dict):
            continue
        fid = str(finding.get('id') or '')
        row = _finding_row(finding)
        if fid in UGLY_FINDING_IDS:
            ugly.append(row)
        else:
            bad.append(row)

    good = _good_rows(registry, registry_status)
    plan['summary'] = {
        'good': good,
        'bad': bad,
        'ugly': ugly,
    }

    good_bits = []
    for row in good:
        label = row.get('label') or row.get('id')
        good_bits.append('{0} {1}/{2}'.format(
            label, int(row.get('ok') or 0), int(row.get('total') or 0)
        ))
    log.info('The good: {0}'.format('; '.join(good_bits) if good_bits else '(none)'))
    if bad:
        log.info('The bad: {0} finding(s)'.format(len(bad)))
        for row in bad:
            log.info('  - {0} ({1})'.format(row.get('title'), row.get('count')))
    else:
        log.info('The bad: none')
    if ugly:
        log.info('The ugly: {0} finding(s)'.format(len(ugly)))
        for row in ugly:
            log.info('  - {0} ({1})'.format(row.get('title'), row.get('count')))
    else:
        log.info('The ugly: none')

    return plan
