# -*- coding: utf-8 -*-
"""Shared job meta heartbeat helpers for publish / repair background jobs."""

from __future__ import print_function

import json
import os
import time


def _meta_path(root, name):
    return os.path.join(root, 'log', name)


def load_job_meta(root, name='build.meta.json'):
    path = _meta_path(root, name)
    if not os.path.isfile(path):
        return {}
    try:
        with open(path, 'r', encoding='utf-8') as handle:
            loaded = json.load(handle)
        return loaded if isinstance(loaded, dict) else {}
    except Exception:
        return {}


def write_job_meta(root, updates, name='build.meta.json', merge=True):
    path = _meta_path(root, name)
    meta = load_job_meta(root, name) if merge else {}
    if not isinstance(updates, dict):
        return meta
    meta.update(updates)
    now = int(time.time())
    if 'started_at' not in meta:
        meta['started_at'] = now
    meta['updated_at'] = now
    meta['heartbeat_at'] = now
    try:
        log_dir = os.path.dirname(path)
        if not os.path.isdir(log_dir):
            os.makedirs(log_dir)
        with open(path, 'w', encoding='utf-8') as handle:
            json.dump(meta, handle, ensure_ascii=False, separators=(',', ':'))
    except Exception:
        pass
    return meta


def touch_heartbeat(root, stage='', message='', name='build.meta.json'):
    updates = {}
    if stage:
        updates['stage'] = stage
    if message:
        updates['message'] = message
    return write_job_meta(root, updates, name=name, merge=True)
