# -*- coding: utf-8 -*-
"""
Site health storage reclaim — stuck package scratch + older Ready Jobs archives.

Policy:
- Never touch media originals or masters.
- Package scratch: leftover .bandpromo-* workdirs (failed/killed Site update / export).
- Ready archives: keep newest Ready job per kind (backup | pcf | pbf); delete older only.
- Skip pending / building / failed-in-progress jobs.
"""

from __future__ import print_function

import json
import os
import shutil

import log
from paths import ROOT_DIR

try:
    from stopflag import stop_requested
except Exception:
    def stop_requested():
        return False


_ROOT_SCRATCH_NAMES = (
    '.bandpromo-update',
    '.bandpromo-demo-release-package',
    '.bandpromo-theme-package',
    '.bandpromo-icons-package',
    '.bandpromo-brand-package',
    '.bandpromo-bootstrap',
)

_BACKUPS_SCRATCH_PREFIXES = (
    '.bandpromo-release-export-',
    '.bandpromo-brand-export-',
)


def _safe(value):
    return str(value or '').strip()


def format_bytes(num):
    try:
        num = int(num)
    except Exception:
        num = 0
    if num < 1024:
        return '{0} B'.format(num)
    if num < 1024 * 1024:
        return '{0:.1f} KB'.format(num / 1024.0)
    if num < 1024 * 1024 * 1024:
        return '{0:.1f} MB'.format(num / (1024.0 * 1024.0))
    return '{0:.2f} GB'.format(num / (1024.0 * 1024.0 * 1024.0))


def _dir_size_bytes(abs_path):
    total = 0
    if not abs_path or not os.path.exists(abs_path):
        return 0
    if os.path.isfile(abs_path):
        try:
            return int(os.path.getsize(abs_path))
        except Exception:
            return 0
    for root, _dirs, files in os.walk(abs_path):
        for name in files:
            path = os.path.join(root, name)
            try:
                total += int(os.path.getsize(path))
            except Exception:
                pass
    return total


def _rel_root(abs_path):
    abs_path = os.path.normpath(abs_path)
    root = os.path.normpath(ROOT_DIR)
    if abs_path == root:
        return '.'
    prefix = root + os.sep
    if abs_path.startswith(prefix):
        return abs_path[len(prefix):].replace('\\', '/')
    return abs_path.replace('\\', '/')


def _backups_dir():
    return os.path.join(ROOT_DIR, 'backups')


def probe_package_scratch():
    """Leftover package / export workdirs. Returns list of candidate dicts."""
    out = []
    for name in _ROOT_SCRATCH_NAMES:
        abs_path = os.path.join(ROOT_DIR, name)
        if not os.path.isdir(abs_path):
            continue
        size = _dir_size_bytes(abs_path)
        out.append({
            'path': _rel_root(abs_path),
            'abs': abs_path,
            'kind': 'package_scratch',
            'size_bytes': size,
            'size_label': format_bytes(size),
            'is_dir': True,
        })

    backups = _backups_dir()
    if os.path.isdir(backups):
        try:
            names = os.listdir(backups)
        except Exception:
            names = []
        for name in names:
            if name in ('.', '..'):
                continue
            matched = False
            for prefix in _BACKUPS_SCRATCH_PREFIXES:
                if name.startswith(prefix):
                    matched = True
                    break
            if not matched:
                continue
            abs_path = os.path.join(backups, name)
            if not os.path.isdir(abs_path):
                continue
            size = _dir_size_bytes(abs_path)
            out.append({
                'path': _rel_root(abs_path),
                'abs': abs_path,
                'kind': 'export_scratch',
                'size_bytes': size,
                'size_label': format_bytes(size),
                'is_dir': True,
            })

    out.sort(key=lambda c: c.get('path') or '')
    return out


def total_size_bytes(candidates):
    total = 0
    for item in candidates or []:
        try:
            total += int(item.get('size_bytes') or 0)
        except Exception:
            pass
    return total


def apply_package_prune(candidates=None):
    """Remove stuck package scratch dirs. Returns (removed, failed)."""
    log.phase('treat:storage_package')
    if candidates is None:
        candidates = probe_package_scratch()

    removed = 0
    failed = 0
    total = len(candidates)
    if total:
        log.info(
            'Clearing {0} leftover package folder(s) ({1})...'.format(
                total, format_bytes(total_size_bytes(candidates)),
            )
        )
        log.items(
            'Package scratch prune',
            [
                '{0} ({1})'.format(c.get('path'), c.get('size_label') or '')
                for c in candidates
            ],
        )

    for index, item in enumerate(candidates, 1):
        if stop_requested():
            log.info('Stop requested during package scratch prune.')
            break
        rel = item.get('path') or ''
        abs_path = item.get('abs') or ''
        log.progress('storage_package_prune', index, max(total, 1), rel)
        if not abs_path or not os.path.exists(abs_path):
            continue
        try:
            if os.path.isdir(abs_path):
                shutil.rmtree(abs_path)
            else:
                os.remove(abs_path)
            removed += 1
            log.info('Removed {0}'.format(rel))
        except Exception as exc:
            failed += 1
            log.info('Could not remove {0}: {1}'.format(rel, exc))

    log.treat_result(
        'storage_package_prune',
        'ok' if failed == 0 else ('partial' if removed else 'failed'),
        removed,
    )
    return removed, failed


def _load_job_meta(meta_path):
    if not os.path.isfile(meta_path):
        return None
    try:
        with open(meta_path, 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
        return payload if isinstance(payload, dict) else None
    except Exception:
        return None


def _job_kind(job):
    """Operator reclaim bucket: backup | pcf | pbf."""
    type_raw = _safe(job.get('type')).lower()
    if type_raw in ('prp', 'pcf'):
        return 'pcf'
    if type_raw == 'pbf':
        return 'pbf'
    filename = _safe(job.get('filename')).lower()
    if filename.endswith('.pcf') or filename.endswith('.prp'):
        return 'pcf'
    if filename.endswith('.pbf'):
        return 'pbf'
    if _safe(job.get('release_id')):
        return 'pcf'
    if _safe(job.get('brand_id')):
        return 'pbf'
    return 'backup'


def _job_kind_label(kind):
    if kind == 'pcf':
        return 'PCF'
    if kind == 'pbf':
        return 'PBF'
    return 'Backup'


def _job_archive_bytes(job, job_id, backups):
    size = 0
    try:
        size = int(job.get('size_bytes') or 0)
    except Exception:
        size = 0
    if size > 0:
        return size
    zip_path = os.path.join(backups, job_id + '.zip')
    if os.path.isfile(zip_path):
        try:
            return int(os.path.getsize(zip_path))
        except Exception:
            pass
    package_upload = _safe(job.get('package_upload_path'))
    if package_upload and os.path.isfile(package_upload):
        try:
            return int(os.path.getsize(package_upload))
        except Exception:
            pass
    return 0


def probe_ready_archives():
    """
    Ready Jobs archives that are older than the newest Ready job of the same kind.

    Returns dict:
      removable: candidates to delete
      kept: newest Ready per kind (informational)
    """
    backups = _backups_dir()
    removable = []
    kept = []
    if not os.path.isdir(backups):
        return {'removable': removable, 'kept': kept}

    by_kind = {}
    try:
        names = os.listdir(backups)
    except Exception:
        names = []

    for name in names:
        if not name.endswith('.json') or name.endswith('.plan.json'):
            continue
        job_id = name[:-5]
        if job_id == '' or '/' in job_id or '\\' in job_id:
            continue
        meta_path = os.path.join(backups, name)
        job = _load_job_meta(meta_path)
        if not isinstance(job, dict):
            continue
        status = _safe(job.get('status')).lower()
        if status != 'ready':
            continue
        kind = _job_kind(job)
        created = _safe(job.get('created_at_utc')) or _safe(job.get('completed_at_utc'))
        size = _job_archive_bytes(job, job_id, backups)
        entry = {
            'job_id': job_id,
            'kind': kind,
            'kind_label': _job_kind_label(kind),
            'status': status,
            'created_at_utc': created,
            'filename': _safe(job.get('filename')),
            'type_label': _safe(job.get('type_label')) or _job_kind_label(kind),
            'size_bytes': size,
            'size_label': format_bytes(size),
            'meta_path': meta_path,
            'path': 'backups/' + job_id + '.zip',
        }
        by_kind.setdefault(kind, []).append(entry)

    for kind, rows in by_kind.items():
        rows.sort(
            key=lambda r: (r.get('created_at_utc') or '', r.get('job_id') or ''),
            reverse=True,
        )
        if not rows:
            continue
        newest = rows[0]
        kept.append(newest)
        for older in rows[1:]:
            removable.append(older)

    removable.sort(key=lambda r: r.get('created_at_utc') or '', reverse=True)
    return {'removable': removable, 'kept': kept}


def _delete_job_files(job_id, job=None):
    """Mirror PHP bandpromo_site_backup_delete_job file cleanup."""
    backups = _backups_dir()
    job_id = _safe(job_id)
    if job_id == '':
        raise ValueError('missing job id')

    paths = [
        os.path.join(backups, job_id + '.plan.json'),
        os.path.join(backups, job_id + '.zip'),
        os.path.join(backups, job_id + '.json'),
        os.path.join(backups, job_id + '.upload.zip'),
        os.path.join(backups, job_id + '.upload.pcf'),
        os.path.join(backups, job_id + '.upload.prp'),
        os.path.join(backups, job_id + '.upload.pbf'),
    ]
    work = os.path.join(backups, '.work', job_id)
    if job and isinstance(job, dict):
        package_upload = _safe(job.get('package_upload_path'))
        if package_upload:
            paths.append(package_upload)

    errors = []
    for path in paths:
        if not path or not os.path.exists(path):
            continue
        try:
            if os.path.isdir(path):
                shutil.rmtree(path)
            else:
                os.remove(path)
        except Exception as exc:
            errors.append('{0}: {1}'.format(path, exc))

    if os.path.isdir(work):
        try:
            shutil.rmtree(work)
        except Exception as exc:
            errors.append('{0}: {1}'.format(work, exc))

    if errors:
        raise RuntimeError('; '.join(errors[:3]))


def apply_archives_prune(probe=None):
    """Delete older Ready archives; keep newest per kind. Returns (removed, failed)."""
    log.phase('treat:storage_archives')
    if probe is None:
        probe = probe_ready_archives()
    removable = probe.get('removable') or []
    kept = probe.get('kept') or []

    removed = 0
    failed = 0
    total = len(removable)
    if kept:
        log.items(
            'Keeping newest Ready archive per kind',
            [
                '{0}: {1} ({2})'.format(
                    k.get('kind_label'),
                    k.get('job_id'),
                    k.get('size_label') or '',
                )
                for k in kept
            ],
        )
    if total:
        log.info(
            'Removing {0} older Ready archive(s) ({1})...'.format(
                total, format_bytes(total_size_bytes(removable)),
            )
        )
        log.items(
            'Older Ready archives to remove',
            [
                '{0} {1} ({2})'.format(
                    r.get('kind_label'),
                    r.get('job_id'),
                    r.get('size_label') or '',
                )
                for r in removable
            ],
        )

    for index, item in enumerate(removable, 1):
        if stop_requested():
            log.info('Stop requested during Ready archive prune.')
            break
        job_id = item.get('job_id') or ''
        label = '{0}/{1}'.format(item.get('kind_label') or '', job_id)
        log.progress('storage_archives_prune', index, max(total, 1), label)
        meta = _load_job_meta(item.get('meta_path') or '')
        try:
            _delete_job_files(job_id, meta)
            removed += 1
            log.info('Removed Ready archive {0}'.format(label))
        except Exception as exc:
            failed += 1
            log.info('Could not remove Ready archive {0}: {1}'.format(label, exc))

    log.treat_result(
        'storage_archives_prune',
        'ok' if failed == 0 else ('partial' if removed else 'failed'),
        removed,
    )
    return removed, failed
