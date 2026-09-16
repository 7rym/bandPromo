# -*- coding: utf-8 -*-
"""Unified HEALTH_* logging contract for site health."""

from __future__ import print_function

import sys


def _safe(text):
    return (
        str(text)
        .replace('\u2026', '...')
        .replace('\u2014', '-')
        .replace('\u2013', '-')
    )


def emit(line):
    """Print one operator/machine log line and flush."""
    text = _safe(line).rstrip('\n')
    try:
        sys.stdout.write(text + '\n')
        sys.stdout.flush()
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
