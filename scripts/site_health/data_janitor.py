# -*- coding: utf-8 -*-
"""
Site health data janitor — ephemeral junk under data/ + orphan containers.

Policy:
- Never touch terces, setup marker, install-preferences, analytics, assets,
  campaigns docs, brands, or site-health plan/fingerprint files.
- Ephemeral junk (OS junk, empty dirs, stale upload_tmp) → Treat prune.
- Invisible container docs that already stamp a valid campaign_id → Treat
  relink (register into the type registry). Association is the campaign_id.
- Invisible without a home, registered unowned, or dangling campaign_id →
  Manual Adopt / Delete (operator chooses; never auto-guess).
- Skip locked demo containers.

Probe is read-only; Treat mutates.
"""

from __future__ import print_function

import json
import os
import time

import log
from paths import ROOT_DIR

try:
    from stopflag import stop_requested
except Exception:
    def stop_requested():
        return False

_PRIMARY = 'primary'
_CONTAINER_TYPES = (
    ('playlist', 'playlists', 'playlists'),
    ('gallery', 'galleries', 'galleries'),
    ('page', 'pages', 'pages'),
)
_JUNK_NAMES = frozenset({
    'desktop.ini',
    'thumbs.db',
    '.ds_store',
    '.gitkeep',
})
# Top-level data names that must never be pruned as empty dirs / walked for junk
# beyond OS junk filenames inside allowed scratch trees.
_PROTECTED_TOP = frozenset({
    'terces',
    '.setup_complete',
    'install-preferences.json',
    'analytics',
    'assets',
    'campaigns',
    'brands',
    'install',
    'site-health-plan.json',
    'site-health-fingerprint.json',
    'site-health-treat-selection.json',
    'registry.json',
})
_UPLOAD_TMP_MAX_AGE_SEC = 24 * 60 * 60


def _safe(value):
    return str(value or '').strip()


def _data_root():
    return os.path.join(ROOT_DIR, 'data')


def _rel_data(abs_path):
    abs_path = os.path.normpath(abs_path)
    root = os.path.normpath(_data_root())
    if abs_path == root:
        return 'data'
    prefix = root + os.sep
    if abs_path.startswith(prefix):
        return ('data/' + abs_path[len(prefix):].replace('\\', '/')).rstrip('/')
    return abs_path.replace('\\', '/')


def _load_json(path):
    if not os.path.isfile(path):
        return None
    try:
        with open(path, 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
        return payload if isinstance(payload, dict) else None
    except Exception:
        return None


def _demo_ids():
    """Protected demo campaign / entity ids (prefs + legacy bandpromo-demo)."""
    ids = set(['bandpromo-demo'])
    prefs = _load_json(os.path.join(_data_root(), 'install-preferences.json')) or {}
    for key in ('demo_campaign_id', 'demo_release_id'):
        raw = _safe(prefs.get(key)).lower()
        if raw:
            ids.add(raw)
    return ids


def _is_demo_entity(entity_id, demo_ids=None):
    eid = _safe(entity_id).lower()
    if eid == '':
        return False
    if demo_ids is None:
        demo_ids = _demo_ids()
    return eid in demo_ids


def _campaign_id_from_doc(doc):
    if not isinstance(doc, dict):
        return ''
    for key in ('campaign_id', 'release_id'):
        raw = _safe(doc.get(key))
        if raw and raw.lower() != _PRIMARY:
            return raw
    return ''


def _home_is_unowned(campaign_id):
    home = _safe(campaign_id)
    return home == '' or home.lower() == _PRIMARY


def _campaign_exists(campaign_id):
    cid = _safe(campaign_id)
    if cid == '' or cid.lower() == _PRIMARY:
        return False
    path = os.path.join(_data_root(), 'campaigns', cid + '.json')
    return os.path.isfile(path)


def _campaign_title(campaign_id):
    cid = _safe(campaign_id)
    if cid == '':
        return ''
    doc = _load_json(os.path.join(_data_root(), 'campaigns', cid + '.json'))
    if not isinstance(doc, dict):
        return cid
    title = _safe(doc.get('title') or doc.get('name'))
    return title if title else cid


def _campaign_is_locked(campaign_id):
    cid = _safe(campaign_id)
    doc = _load_json(os.path.join(_data_root(), 'campaigns', cid + '.json'))
    if not isinstance(doc, dict):
        return False
    return bool(doc.get('locked'))


def _list_adopt_campaigns(demo_ids):
    """Operator campaigns available for Adopt pickers (skip primary / demo / missing)."""
    registry = _load_json(os.path.join(_data_root(), 'campaigns', 'registry.json')) or {}
    entries = registry.get('releases') if isinstance(registry, dict) else None
    if not isinstance(entries, list):
        entries = []
    out = []
    seen = set()
    for entry in entries:
        if not isinstance(entry, dict):
            continue
        cid = _safe(entry.get('id'))
        if cid == '' or cid.lower() == _PRIMARY:
            continue
        if _is_demo_entity(cid, demo_ids):
            continue
        if not _campaign_exists(cid):
            continue
        if _campaign_is_locked(cid):
            continue
        low = cid.lower()
        if low in seen:
            continue
        seen.add(low)
        title = _safe(entry.get('title')) or _campaign_title(cid)
        out.append({'id': cid, 'title': title or cid})
    out.sort(key=lambda c: (c.get('title') or c.get('id') or '').lower())
    return out


def _registry_ids(dirname, list_key):
    payload = _load_json(os.path.join(_data_root(), dirname, 'registry.json'))
    if not isinstance(payload, dict):
        return set(), {}
    entries = payload.get(list_key)
    by_id = {}
    if isinstance(entries, list):
        for entry in entries:
            if not isinstance(entry, dict):
                continue
            eid = _safe(entry.get('id'))
            if eid:
                by_id[eid] = entry
    return set(by_id.keys()), by_id


def _doc_title(doc, fallback):
    if isinstance(doc, dict):
        title = _safe(doc.get('title') or doc.get('name'))
        if title:
            return title
    return fallback


def _add_ephemeral(out, abs_path, kind, reason):
    if not abs_path or not os.path.exists(abs_path):
        return
    rel = _rel_data(abs_path)
    for existing in out:
        if existing.get('path') == rel:
            return
    # Never prune protected top-level paths themselves.
    parts = [p for p in rel.split('/') if p]
    if len(parts) >= 2 and parts[1] in _PROTECTED_TOP:
        # Allow junk *files* inside protected trees? No — skip entire protected trees
        # except upload_tmp / validation scratch handled separately.
        if parts[1] not in ('upload_tmp', 'validation', 'delivery'):
            return
    out.append({
        'path': rel,
        'abs': abs_path,
        'kind': kind,
        'reason': reason,
        'is_dir': os.path.isdir(abs_path),
    })


def probe_ephemeral_targets():
    """OS junk, stale upload_tmp files, empty non-protected dirs under data/."""
    out = []
    root = _data_root()
    if not os.path.isdir(root):
        return out

    now = time.time()
    upload_tmp = os.path.join(root, 'upload_tmp')
    if os.path.isdir(upload_tmp):
        try:
            names = os.listdir(upload_tmp)
        except Exception:
            names = []
        for name in names:
            if name in ('.', '..'):
                continue
            path = os.path.join(upload_tmp, name)
            low = name.lower()
            if low in _JUNK_NAMES:
                _add_ephemeral(out, path, 'junk', 'non-media junk under upload_tmp')
                continue
            try:
                age = now - os.path.getmtime(path)
            except Exception:
                age = 0
            if age >= _UPLOAD_TMP_MAX_AGE_SEC:
                _add_ephemeral(
                    out, path, 'stale_upload_tmp',
                    'upload_tmp older than 24h',
                )

    # Walk data/ for junk filenames; skip protected top-level trees entirely.
    for dirpath, dirnames, filenames in os.walk(root):
        rel_dir = _rel_data(dirpath)
        parts = [p for p in rel_dir.split('/') if p]
        top = parts[1] if len(parts) >= 2 else ''
        if top in _PROTECTED_TOP and top not in ('upload_tmp', 'validation', 'delivery'):
            dirnames[:] = []
            continue
        # Do not descend into analytics etc.
        dirnames[:] = [
            d for d in dirnames
            if d.lower() not in (
                'analytics', 'assets', 'campaigns', 'brands', 'install',
            )
        ]
        for name in filenames:
            if name.lower() in _JUNK_NAMES:
                _add_ephemeral(
                    out, os.path.join(dirpath, name), 'junk',
                    'OS junk under data/',
                )

    # Empty directories (deepest first later at treat). Skip protected roots.
    for dirpath, dirnames, filenames in os.walk(root, topdown=False):
        rel_dir = _rel_data(dirpath)
        parts = [p for p in rel_dir.split('/') if p]
        if rel_dir == 'data' or len(parts) < 2:
            continue
        top = parts[1]
        if top in _PROTECTED_TOP and top not in ('upload_tmp', 'validation', 'delivery'):
            continue
        if top in ('playlists', 'galleries', 'pages'):
            # Never remove container folders as "empty" — registry lives here.
            continue
        if top in ('upload_tmp', 'validation', 'delivery') and len(parts) == 2:
            # Keep the structural scratch roots even when empty.
            continue
        try:
            if not os.listdir(dirpath):
                _add_ephemeral(out, dirpath, 'empty_dir', 'empty folder under data/')
        except Exception:
            continue

    return out


def _is_protected_container(kind, entity_id, demo_ids=None):
    """Skip demo entities and required/system shell pages."""
    eid = _safe(entity_id).lower()
    if eid == '':
        return True
    if _is_demo_entity(eid, demo_ids):
        return True
    # Install shell pages are intentionally unowned (not campaign catalogue).
    if kind == 'page' and eid in ('faq', 'bio', 'gallery'):
        return True
    return False


def probe_containers(demo_ids=None):
    """
    Return {
      'relinkable': [...],  # invisible + valid campaign_id → register
      'registry_stubs': [...],  # registry id, missing doc → drop registry row
      'manual': [...],  # Adopt/Delete rows
    }
    """
    if demo_ids is None:
        demo_ids = _demo_ids()
    adopt_campaigns = _list_adopt_campaigns(demo_ids)
    relinkable = []
    stubs = []
    manual = []

    for kind, dirname, list_key in _CONTAINER_TYPES:
        folder = os.path.join(_data_root(), dirname)
        if not os.path.isdir(folder):
            continue
        reg_ids, reg_by_id = _registry_ids(dirname, list_key)
        try:
            names = os.listdir(folder)
        except Exception:
            names = []

        disk_ids = set()
        for name in names:
            if not name.endswith('.json') or name == 'registry.json':
                continue
            entity_id = name[:-5]
            if entity_id == '':
                continue
            disk_ids.add(entity_id)
            if _is_protected_container(kind, entity_id, demo_ids):
                continue
            path = os.path.join(folder, name)
            doc = _load_json(path) or {}
            title = _doc_title(doc, entity_id)
            campaign_id = _campaign_id_from_doc(doc)
            rel = 'data/{0}/{1}'.format(dirname, name)

            if entity_id not in reg_ids:
                # Invisible on-disk doc.
                if (
                    campaign_id
                    and _campaign_exists(campaign_id)
                    and not _campaign_is_locked(campaign_id)
                    and not _is_demo_entity(campaign_id, demo_ids)
                ):
                    relinkable.append({
                        'kind': kind,
                        'id': entity_id,
                        'title': title,
                        'path': rel,
                        'campaign_id': campaign_id,
                        'campaign_title': _campaign_title(campaign_id),
                        'class': 'invisible_with_home',
                    })
                else:
                    manual.append({
                        'kind': kind,
                        'id': entity_id,
                        'title': title,
                        'path': rel,
                        'campaign_id': campaign_id,
                        'campaign_title': _campaign_title(campaign_id) if campaign_id else '',
                        'class': 'invisible',
                        'in_registry': False,
                        'allow_delete': True,
                        'campaigns': adopt_campaigns,
                    })
                continue

            # Registered + on disk.
            if _home_is_unowned(campaign_id):
                manual.append({
                    'kind': kind,
                    'id': entity_id,
                    'title': title,
                    'path': rel,
                    'campaign_id': '',
                    'campaign_title': '',
                    'class': 'unowned',
                    'in_registry': True,
                    'allow_delete': True,
                    'campaigns': adopt_campaigns,
                })
            elif not _campaign_exists(campaign_id):
                manual.append({
                    'kind': kind,
                    'id': entity_id,
                    'title': title,
                    'path': rel,
                    'campaign_id': campaign_id,
                    'campaign_title': campaign_id,
                    'class': 'dangling',
                    'in_registry': True,
                    'allow_delete': True,
                    'campaigns': adopt_campaigns,
                })

        for entity_id in sorted(reg_ids):
            if entity_id in disk_ids:
                continue
            if _is_protected_container(kind, entity_id, demo_ids):
                continue
            entry = reg_by_id.get(entity_id) or {}
            title = _safe(entry.get('title')) or entity_id
            stubs.append({
                'kind': kind,
                'id': entity_id,
                'title': title,
                'path': 'data/{0}/registry.json#{1}'.format(dirname, entity_id),
                'class': 'registry_stub',
            })

    relinkable.sort(key=lambda r: (r.get('kind') or '', r.get('id') or ''))
    stubs.sort(key=lambda r: (r.get('kind') or '', r.get('id') or ''))
    manual.sort(key=lambda r: (r.get('kind') or '', r.get('id') or ''))
    return {
        'relinkable': relinkable,
        'registry_stubs': stubs,
        'manual': manual,
    }


def probe_all():
    return {
        'ephemeral': probe_ephemeral_targets(),
        'containers': probe_containers(),
    }


def format_manual_sample_row(row):
    if not isinstance(row, dict):
        return None
    return {
        'kind': _safe(row.get('kind')),
        'id': _safe(row.get('id')),
        'title': _safe(row.get('title')) or _safe(row.get('id')),
        'path': _safe(row.get('path')),
        'class': _safe(row.get('class')) or 'invisible',
        'campaign_id': _safe(row.get('campaign_id')),
        'campaign_title': _safe(row.get('campaign_title')),
        'in_registry': bool(row.get('in_registry')),
        'allow_delete': bool(row.get('allow_delete', True)),
        'campaigns': row.get('campaigns') if isinstance(row.get('campaigns'), list) else [],
    }


def format_manual_log_line(row):
    sample = format_manual_sample_row(row)
    if not sample:
        return ''
    return '{0} {1} ({2}) — {3}{4}'.format(
        sample.get('kind'),
        sample.get('title') or sample.get('id'),
        sample.get('class'),
        sample.get('path'),
        (
            ' — was: {0}'.format(sample.get('campaign_title') or sample.get('campaign_id'))
            if sample.get('campaign_id')
            else ''
        ),
    )


def _registry_remove_id(dirname, list_key, entity_id):
    path = os.path.join(_data_root(), dirname, 'registry.json')
    payload = _load_json(path)
    if not isinstance(payload, dict):
        return False
    entries = payload.get(list_key)
    if not isinstance(entries, list):
        return False
    before = len(entries)
    payload[list_key] = [
        e for e in entries
        if not (isinstance(e, dict) and _safe(e.get('id')) == entity_id)
    ]
    if len(payload[list_key]) == before:
        return False
    try:
        with open(path, 'w', encoding='utf-8', newline='\n') as handle:
            json.dump(payload, handle, ensure_ascii=False, indent=2)
            handle.write('\n')
        return True
    except Exception:
        return False


def _registry_append(dirname, list_key, entry):
    path = os.path.join(_data_root(), dirname, 'registry.json')
    payload = _load_json(path)
    if not isinstance(payload, dict):
        payload = {'version': 1, list_key: []}
    entries = payload.get(list_key)
    if not isinstance(entries, list):
        entries = []
    eid = _safe(entry.get('id'))
    entries = [
        e for e in entries
        if not (isinstance(e, dict) and _safe(e.get('id')) == eid)
    ]
    entries.append(entry)
    payload[list_key] = entries
    try:
        with open(path, 'w', encoding='utf-8', newline='\n') as handle:
            json.dump(payload, handle, ensure_ascii=False, indent=2)
            handle.write('\n')
        return True
    except Exception:
        return False


def apply_ephemeral_prune(candidates=None):
    """Delete ephemeral junk. Returns (removed, failed)."""
    log.phase('treat:data_janitor')
    if candidates is None:
        candidates = probe_ephemeral_targets()

    files = [c for c in candidates if not c.get('is_dir')]
    dirs = [c for c in candidates if c.get('is_dir')]
    dirs.sort(key=lambda c: c.get('path') or '', reverse=True)
    ordered = files + dirs

    removed = 0
    failed = 0
    total = len(ordered)
    if total:
        log.info('Pruning {0} data leftover(s)...'.format(total))
        log.items('Data janitor prune', [c.get('path') for c in ordered])

    for index, item in enumerate(ordered, 1):
        if stop_requested():
            log.info('Stop requested during data janitor.')
            break
        rel = item.get('path') or ''
        abs_path = item.get('abs') or ''
        log.progress('data_janitor_prune', index, max(total, 1), rel)
        if not abs_path or not os.path.exists(abs_path):
            continue
        try:
            if os.path.isdir(abs_path):
                os.rmdir(abs_path)
            else:
                os.remove(abs_path)
            removed += 1
        except Exception as exc:
            failed += 1
            log.info('Could not remove {0}: {1}'.format(rel, exc))

    log.treat_result(
        'data_janitor_prune',
        'ok' if failed == 0 else ('partial' if removed else 'failed'),
        removed,
    )
    return removed, failed


def apply_container_relink(probe=None):
    """
    Register invisible docs that already have a valid campaign home,
    and drop registry stubs with no document. Returns (fixed, failed).
    """
    log.phase('treat:data_container_relink')
    if probe is None:
        probe = probe_containers()
    relinkable = probe.get('relinkable') or []
    stubs = probe.get('registry_stubs') or []
    fixed = 0
    failed = 0

    if relinkable:
        log.items(
            'Registering invisible containers with a campaign home',
            [
                '{0}/{1} → {2}'.format(r.get('kind'), r.get('id'), r.get('campaign_id'))
                for r in relinkable
            ],
        )
    if stubs:
        log.items(
            'Dropping registry stubs with no document',
            ['{0}/{1}'.format(r.get('kind'), r.get('id')) for r in stubs],
        )

    kind_map = {
        'playlist': ('playlists', 'playlists'),
        'gallery': ('galleries', 'galleries'),
        'page': ('pages', 'pages'),
    }

    for row in relinkable:
        if stop_requested():
            log.info('Stop requested during container relink.')
            break
        kind = _safe(row.get('kind'))
        entity_id = _safe(row.get('id'))
        title = _safe(row.get('title')) or entity_id
        dirname_key = kind_map.get(kind)
        if not dirname_key or entity_id == '':
            failed += 1
            continue
        dirname, list_key = dirname_key
        entry = {
            'id': entity_id,
            'title': title,
            'kind': 'system',
            'publish_date': '',
            'sort_order': 100,
        }
        if _registry_append(dirname, list_key, entry):
            fixed += 1
            log.info('Registered {0} {1} (home {2}).'.format(
                kind, entity_id, row.get('campaign_id') or '',
            ))
        else:
            failed += 1

    for row in stubs:
        if stop_requested():
            break
        kind = _safe(row.get('kind'))
        entity_id = _safe(row.get('id'))
        dirname_key = kind_map.get(kind)
        if not dirname_key or entity_id == '':
            failed += 1
            continue
        dirname, list_key = dirname_key
        if _registry_remove_id(dirname, list_key, entity_id):
            fixed += 1
            log.info('Dropped registry stub {0} {1}.'.format(kind, entity_id))
        else:
            failed += 1

    if not relinkable and not stubs:
        log.info('No unambiguous container relinks.')

    log.treat_result(
        'data_container_relink',
        'ok' if failed == 0 else ('partial' if fixed else 'failed'),
        fixed,
    )
    return fixed, failed
