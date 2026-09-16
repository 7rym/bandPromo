# -*- coding: utf-8 -*-
"""Read/write site-health-plan.json."""

from __future__ import print_function

import json
import os
from datetime import datetime

from paths import PLAN_PATH, ROOT_DIR, VERSION_PATH


def read_app_version():
    if not os.path.isfile(VERSION_PATH):
        return ''
    try:
        with open(VERSION_PATH, 'r', encoding='utf-8') as handle:
            return handle.read().strip()
    except Exception:
        return ''


def empty_plan():
    return {
        'version': 1,
        'checked_at': '',
        'host_fingerprint': '',
        'app_version': read_app_version(),
        'overall': 'healthy',
        'findings': [],
        'treatments': [],
        'mode': 'check',
    }


def load_plan():
    if not os.path.isfile(PLAN_PATH):
        return empty_plan()
    try:
        with open(PLAN_PATH, 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
        if isinstance(payload, dict):
            return payload
    except Exception:
        pass
    return empty_plan()


def save_plan(plan):
    data_dir = os.path.join(ROOT_DIR, 'data')
    if not os.path.isdir(data_dir):
        os.makedirs(data_dir)
    plan = dict(plan) if isinstance(plan, dict) else empty_plan()
    plan['checked_at'] = plan.get('checked_at') or datetime.utcnow().strftime('%Y-%m-%dT%H:%M:%SZ')
    plan['app_version'] = plan.get('app_version') or read_app_version()
    tmp = PLAN_PATH + '.tmp'
    with open(tmp, 'w', encoding='utf-8', newline='\n') as handle:
        json.dump(plan, handle, ensure_ascii=False, indent=2)
        handle.write('\n')
    if os.path.isfile(PLAN_PATH):
        os.replace(tmp, PLAN_PATH)
    else:
        os.rename(tmp, PLAN_PATH)
    return plan


def add_finding(plan, finding_id, severity, title, count, treatment, sample=None, body=''):
    findings = plan.setdefault('findings', [])
    item = {
        'id': finding_id,
        'severity': severity,
        'title': title,
        'body': body,
        'count': int(count),
        'treatment': treatment,
        'items_sample': list(sample or [])[:12],
    }
    findings.append(item)
    treatments = plan.setdefault('treatments', [])
    if treatment and not any(t.get('id') == treatment for t in treatments):
        treatments.append({
            'id': treatment,
            'label': title,
            'mutates': True,
        })
    return plan


def overall_from_findings(plan):
    findings = plan.get('findings') if isinstance(plan.get('findings'), list) else []
    if not findings:
        plan['overall'] = 'healthy'
        return plan
    severities = [str(f.get('severity') or '').lower() for f in findings]
    if 'critical' in severities:
        plan['overall'] = 'critical'
    else:
        plan['overall'] = 'attention'
    return plan
