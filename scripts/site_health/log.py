# -*- coding: utf-8 -*-
"""
Site health Activity log — sole writer of log/site-health.log.

All Check / Treat / Force scripts must log through this module (phase / info /
finding / progress / treat_result / result). PHP must not write the Activity
log; it may only read it for Status, and may keep a private runner sidecar
for process EXITCODE outside the operator pane.
"""

from __future__ import print_function

import os
import sys
from datetime import datetime

_log_fp = None


def _safe(text):
    return (
        str(text)
        .replace('\u2026', '...')
        .replace('\u2014', '-')
        .replace('\u2013', '-')
    )


def _utc_stamp():
    return datetime.utcnow().strftime('%Y-%m-%d %H:%M:%SZ')


def begin_run(log_path):
    """Truncate and open the Activity log for this job (Python-owned)."""
    global _log_fp
    close_run()
    path = str(log_path or '').strip()
    if path == '':
        return
    folder = os.path.dirname(path)
    if folder and not os.path.isdir(folder):
        os.makedirs(folder)
    try:
        _log_fp = open(path, 'w', encoding='utf-8', newline='\n')
    except Exception:
        _log_fp = None


def close_run():
    global _log_fp
    if _log_fp is not None:
        try:
            _log_fp.close()
        except Exception:
            pass
    _log_fp = None


def emit(line):
    """
    Write one Activity line with a UTC timestamp.

    Format: [YYYY-MM-DD HH:MM:SSZ] message
    Machine tokens (HEALTH_PHASE, HEALTH_RESULT, …) stay in the message body
    so parsers can still match on the line.
    """
    text = _safe(line).rstrip('\n')
    # Avoid double-stamping if a caller already prefixed a stamp.
    if text.startswith('[') and 'Z] ' in text[:28]:
        stamped = text
    else:
        stamped = '[{0}] {1}'.format(_utc_stamp(), text)
    try:
        sys.stdout.write(stamped + '\n')
        sys.stdout.flush()
    except Exception:
        try:
            import stdio_utf8
            stdio_utf8.repair()
            sys.stdout.write(stamped + '\n')
            sys.stdout.flush()
        except Exception:
            pass
    if _log_fp is not None:
        try:
            _log_fp.write(stamped + '\n')
            _log_fp.flush()
        except Exception:
            pass


def phase(name):
    emit('HEALTH_PHASE:{0}'.format(name))


def finding(severity, finding_id, count, title):
    emit('HEALTH_FINDING:{0}:{1}:{2}:{3}'.format(
        severity, finding_id, int(count), _safe(title).replace(':', ',')
    ))


def progress(treatment_id, current, total, message):
    emit('HEALTH_PROGRESS:{0}:{1}/{2}:{3}'.format(
        treatment_id, int(current), int(total), _safe(message).replace(':', ',')
    ))


def treat_result(treatment_id, status, count):
    emit('HEALTH_TREAT:{0}:{1}:{2}'.format(treatment_id, status, int(count)))


def result(outcome):
    emit('HEALTH_RESULT:{0}'.format(outcome))


def info(message):
    emit(_safe(message))


def items(label, values, limit=12):
    """Log a labelled list of concrete finding items via the shared logger."""
    names = []
    for value in values or []:
        if isinstance(value, dict):
            name = (
                value.get('master_filename')
                or value.get('asset_id')
                or value.get('name')
                or ''
            )
        else:
            name = value
        name = str(name or '').strip()
        if name:
            names.append(name)
    info('{0}: {1}'.format(label, len(names)))
    shown = names[: int(limit)]
    for name in shown:
        info('  - {0}'.format(name))
    if len(names) > int(limit):
        info('  - ... {0} more'.format(len(names) - int(limit)))
