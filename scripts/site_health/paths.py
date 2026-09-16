# -*- coding: utf-8 -*-
"""Shared paths and constants for site health (Python 3.6+)."""

from __future__ import print_function

import os

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
SCRIPTS_DIR = os.path.dirname(SCRIPT_DIR)
ROOT_DIR = os.path.dirname(SCRIPTS_DIR)

PLAN_PATH = os.path.join(ROOT_DIR, 'data', 'site-health-plan.json')
FINGERPRINT_CACHE_PATH = os.path.join(ROOT_DIR, 'data', 'site-health-fingerprint.json')
LOG_PATH = os.path.join(ROOT_DIR, 'log', 'site-health.log')
META_NAME = 'site-health.meta.json'
STOP_FLAG = os.path.join(ROOT_DIR, 'log', 'site-health.stop')

REGISTRY_PATH = os.path.join(ROOT_DIR, 'data', 'assets', 'registry.json')
VERSION_PATH = os.path.join(ROOT_DIR, 'VERSION')

AUDIO_MASTER_DIR = os.path.join(ROOT_DIR, 'media', 'audio', 'master')
AUDIO_ORIGINAL_DIR = os.path.join(ROOT_DIR, 'media', 'audio', 'original')
AUDIO_OPTIMAL_DIR = os.path.join(ROOT_DIR, 'media', 'audio', 'optimal')
VISUAL_MASTER_DIR = os.path.join(ROOT_DIR, 'media', 'visual', 'master')

AUDIO_EXTS = ('.flac', '.mp3', '.wav')
VISUAL_EXTS = ('.jpg', '.jpeg', '.png', '.webp', '.gif', '.mkv', '.mp4', '.webm')

JSON_FINGERPRINT_PATHS = (
    ('registry', REGISTRY_PATH),
    ('web_config', os.path.join(ROOT_DIR, 'web-config.json')),
    ('releases_registry', os.path.join(ROOT_DIR, 'data', 'releases', 'registry.json')),
    ('playlists_registry', os.path.join(ROOT_DIR, 'data', 'playlists', 'registry.json')),
    ('brands_registry', os.path.join(ROOT_DIR, 'data', 'brands', 'registry.json')),
    ('pages_registry', os.path.join(ROOT_DIR, 'data', 'pages', 'registry.json')),
    ('galleries_registry', os.path.join(ROOT_DIR, 'data', 'galleries', 'registry.json')),
)
