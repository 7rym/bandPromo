# -*- coding: utf-8 -*-
"""
Sticky delivery-rebuild failures for Site health Diagnosis.

Force / Treat can fail ffmpeg (or similar) while an older player stream is still
on disk, so the follow-up check looks healthy. Persist named failures so
Diagnosis / The bad stay truthful until a later delivery run succeeds.
"""

from __future__ import print_function

import json
import os

import plan as plan_mod
from paths import ROOT_DIR

FAILURES_PATH = os.path.join(ROOT_DIR, 'data', 'site-health-delivery-failures.json')


def _empty():
    return {'video': []}


def load():
    if not os.path.isfile(FAILURES_PATH):
        return _empty()
    try:
        with open(FAILURES_PATH, 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
        if not isinstance(payload, dict):
            return _empty()
        video = payload.get('video')
        if not isinstance(video, list):
            video = []
        cleaned = []
        for row in video:
            if isinstance(row, dict):
                name = str(row.get('name') or '').strip()
                reason = str(row.get('reason') or '').strip()
                if name:
                    cleaned.append({'name': name, 'reason': reason})
            else:
                name = str(row or '').strip()
                if name:
                    cleaned.append({'name': name, 'reason': ''})
        return {'video': cleaned}
    except Exception:
        return _empty()


def save(payload):
    data_dir = os.path.join(ROOT_DIR, 'data')
    if not os.path.isdir(data_dir):
        os.makedirs(data_dir)
    data = payload if isinstance(payload, dict) else _empty()
    video = data.get('video') if isinstance(data.get('video'), list) else []
    if not video:
        clear()
        return
    tmp = FAILURES_PATH + '.tmp'
    with open(tmp, 'w', encoding='utf-8', newline='\n') as handle:
        json.dump({'video': video}, handle, ensure_ascii=False, indent=2)
        handle.write('\n')
    if os.path.isfile(FAILURES_PATH):
        os.replace(tmp, FAILURES_PATH)
    else:
        os.rename(tmp, FAILURES_PATH)


def clear():
    try:
        if os.path.isfile(FAILURES_PATH):
            os.remove(FAILURES_PATH)
    except Exception:
        pass


def record_video_failures(rows):
    """
    Replace the sticky video failure list.
    rows: iterable of dicts with name/reason, or "name — reason" strings.
    """
    cleaned = []
    seen = set()
    for row in rows or []:
        if isinstance(row, dict):
            name = str(row.get('name') or '').strip()
            reason = str(row.get('reason') or '').strip()
        else:
            text = str(row or '').strip()
            if ' — ' in text:
                name, reason = text.split(' — ', 1)
            elif ' - ' in text:
                name, reason = text.split(' - ', 1)
            else:
                name, reason = text, ''
            name = name.strip()
            reason = reason.strip()
        key = name.lower()
        if not name or key in seen:
            continue
        seen.add(key)
        cleaned.append({'name': name, 'reason': reason})
    if cleaned:
        save({'video': cleaned})
    else:
        clear()


def clear_video_failures():
    clear()


def apply_to_plan(plan):
    """
    Attach sticky video rebuild failures as Diagnosis findings.
    Returns the plan (may be unchanged).
    """
    payload = load()
    video = payload.get('video') or []
    if not video:
        return plan

    findings = plan.get('findings') if isinstance(plan.get('findings'), list) else []
    if any(str(f.get('id') or '') == 'video_delivery_failed' for f in findings if isinstance(f, dict)):
        return plan

    sample = [row.get('name') for row in video if row.get('name')]
    reasons = []
    for row in video[:5]:
        name = row.get('name') or ''
        reason = row.get('reason') or ''
        if name and reason:
            reasons.append('{0} — {1}'.format(name, reason))
        elif name:
            reasons.append(name)

    body = (
        '{0} video(s) could not be rebuilt for the player. '
        'An older stream may still show as Ready in Files. '
        'Open Files → Visual, confirm each master opens (re-upload if damaged), '
        'then Review treatment and Apply — or run Force again. '
        'Detail is in Activity.'
    ).format(len(video))
    if reasons:
        body = body + ' Examples: ' + '; '.join(reasons) + '.'

    plan_mod.add_finding(
        plan,
        'video_delivery_failed',
        'attention',
        'Some videos could not be rebuilt for the player',
        len(video),
        'listener_delivery',
        sample=sample,
        body=body,
    )
    return plan
