# -*- coding: utf-8 -*-
"""Unified HEALTH_* logging contract for site health."""

from __future__ import print_function

import os
import sys

_log_fp = None


def _safe(text):
    return (
        str(text)
        .replace('\u2026', '...')
        .replace('\u2014', '-')
        .replace('\u2013', '-')
    )


def begin_run(log_path):
    """
    Start a fresh Activity log for this job.

    Python owns log/site-health.log so plan + Activity always match.
    Admin launches redirect build-runner stdout to a side runner log;
    CLI still sees lines on stdout.
    """
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
    """Print one operator/machine log line and flush."""
    text = _safe(line).rstrip('\n')
    try:
        sys.stdout.write(text + '\n')
        sys.stdout.flush()
    except Exception:
        pass
    if _log_fp is not None:
        try:
            _log_fp.write(text + '\n')
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
