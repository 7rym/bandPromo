# -*- coding: utf-8 -*-
"""Shared publish-pipeline counters for the final build summary.

Stages emit one line (hidden from the operator log by build.py):

    BUILD_STATS scope=media handled=12 created=3 fresh=9 failed=0

Scopes:
- media     audio / visual delivery
- playlist  player playlist payloads
- social    share images
- manifest  site.webmanifest
- catalog   master/catalog prep (handled only)

Counters:
- handled: items considered
- created: newly written or updated
- fresh: already up to date
- failed: hard failures
"""

import sys

KNOWN_SCOPES = ('media', 'playlist', 'social', 'manifest', 'catalog')
KNOWN_COUNTERS = ('handled', 'created', 'fresh', 'failed')


def emit_build_stats(handled=None, created=None, fresh=None, failed=None, scope='media'):
    scope = str(scope or 'media').strip().lower() or 'media'
    if scope not in KNOWN_SCOPES:
        scope = 'media'
    parts = ['scope={0}'.format(scope)]
    for key, value in (
        ('handled', handled),
        ('created', created),
        ('fresh', fresh),
        ('failed', failed),
    ):
        if value is None:
            continue
        try:
            parts.append('{0}={1}'.format(key, int(value)))
        except (TypeError, ValueError):
            continue
    if len(parts) <= 1:
        return
    print('BUILD_STATS ' + ' '.join(parts))
    sys.stdout.flush()


def parse_build_stats_line(line):
    """Return a stats dict if line is BUILD_STATS…, else None."""
    if line is None:
        return None
    text = str(line).strip()
    if not text.startswith('BUILD_STATS'):
        return None
    rest = text[len('BUILD_STATS'):].lstrip(' :\t')
    out = {'scope': 'media'}
    for part in rest.split():
        if '=' not in part:
            continue
        key, raw = part.split('=', 1)
        key = key.strip()
        raw = raw.strip()
        if key == 'scope':
            scope = raw.lower()
            out['scope'] = scope if scope in KNOWN_SCOPES else 'media'
            continue
        if key not in KNOWN_COUNTERS:
            continue
        try:
            out[key] = int(raw)
        except (TypeError, ValueError):
            continue
    if not any(k in out for k in KNOWN_COUNTERS):
        return None
    return out


def empty_scope_stats():
    return {
        'handled': 0,
        'created': 0,
        'fresh': 0,
        'failed': 0,
    }


def empty_build_stats():
    return {scope: empty_scope_stats() for scope in KNOWN_SCOPES}


def merge_build_stats(total, partial):
    if not isinstance(total, dict):
        total = empty_build_stats()
    if not isinstance(partial, dict):
        return total

    # Merge another full totals blob (from a stage runner).
    looks_like_totals = any(
        scope in partial and isinstance(partial.get(scope), dict)
        for scope in KNOWN_SCOPES
    )
    if looks_like_totals and 'scope' not in partial:
        for scope in KNOWN_SCOPES:
            bucket = partial.get(scope)
            if not isinstance(bucket, dict):
                continue
            if scope not in total or not isinstance(total.get(scope), dict):
                total[scope] = empty_scope_stats()
            for key in KNOWN_COUNTERS:
                try:
                    total[scope][key] = int(total[scope].get(key) or 0) + int(bucket.get(key) or 0)
                except (TypeError, ValueError):
                    continue
        return total

    # Legacy flat payload (no scope): treat as media.
    if 'scope' not in partial and any(k in partial for k in KNOWN_COUNTERS):
        partial = dict(partial)
        partial['scope'] = 'media'

    scope = str(partial.get('scope') or 'media').strip().lower() or 'media'
    if scope not in KNOWN_SCOPES:
        scope = 'media'
    if scope not in total or not isinstance(total.get(scope), dict):
        total[scope] = empty_scope_stats()

    bucket = total[scope]
    for key in KNOWN_COUNTERS:
        if key not in partial:
            continue
        try:
            bucket[key] = int(bucket.get(key) or 0) + int(partial.get(key) or 0)
        except (TypeError, ValueError):
            continue
    return total


def scope_totals(stats, scope):
    bucket = (stats or {}).get(scope) if isinstance(stats, dict) else None
    if not isinstance(bucket, dict):
        return empty_scope_stats()
    out = empty_scope_stats()
    for key in KNOWN_COUNTERS:
        try:
            out[key] = int(bucket.get(key) or 0)
        except (TypeError, ValueError):
            out[key] = 0
    return out
