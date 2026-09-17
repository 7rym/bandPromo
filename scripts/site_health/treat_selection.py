# -*- coding: utf-8 -*-
"""Operator-selected treatments for Site health Apply."""

from __future__ import print_function

import json
import os

from paths import TREAT_SELECTION_PATH, ROOT_DIR


def clear_selection():
    if os.path.isfile(TREAT_SELECTION_PATH):
        try:
            os.remove(TREAT_SELECTION_PATH)
        except Exception:
            pass


def save_selection(treatment_ids, finding_ids=None):
    """Write selection before Treat launch. Empty list means Apply nothing."""
    data_dir = os.path.join(ROOT_DIR, 'data')
    if not os.path.isdir(data_dir):
        os.makedirs(data_dir)
    clean = []
    seen = set()
    for raw in treatment_ids or []:
        tid = str(raw or '').strip()
        if not tid or tid in seen:
            continue
        seen.add(tid)
        clean.append(tid)
    findings = []
    for raw in finding_ids or []:
        fid = str(raw or '').strip()
        if fid:
            findings.append(fid)
    payload = {
        'treatment_ids': clean,
        'finding_ids': findings,
    }
    tmp = TREAT_SELECTION_PATH + '.tmp'
    with open(tmp, 'w', encoding='utf-8', newline='\n') as handle:
        json.dump(payload, handle, ensure_ascii=False, indent=2)
        handle.write('\n')
    if os.path.isfile(TREAT_SELECTION_PATH):
        os.replace(tmp, TREAT_SELECTION_PATH)
    else:
        os.rename(tmp, TREAT_SELECTION_PATH)
    return payload


def load_selection():
    """
    Return set of treatment ids, or None if no selection file (treat whole plan).
    Empty set means the operator unchecked everything.
    """
    if not os.path.isfile(TREAT_SELECTION_PATH):
        return None
    try:
        with open(TREAT_SELECTION_PATH, 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
    except Exception:
        return None
    if not isinstance(payload, dict):
        return None
    ids = payload.get('treatment_ids')
    if not isinstance(ids, list):
        return set()
    return set(str(i or '').strip() for i in ids if str(i or '').strip())
