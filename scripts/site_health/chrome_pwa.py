# -*- coding: utf-8 -*-
"""Write site.webmanifest from web-config.json (site chrome)."""

from __future__ import print_function

import json
import os

import log
from paths import ROOT_DIR

CONFIG_FILE = os.path.join(ROOT_DIR, 'web-config.json')
MANIFEST_FILE = os.path.join(ROOT_DIR, 'site.webmanifest')


def generate_manifest():
    """
    Build or refresh site.webmanifest.
    Returns True on success (including already up to date).
    """
    log.info('Generating site.webmanifest...')

    config = {}
    if os.path.isfile(CONFIG_FILE):
        try:
            with open(CONFIG_FILE, 'r', encoding='utf-8') as handle:
                loaded = json.load(handle)
            if isinstance(loaded, dict):
                config = loaded
            log.info('Loaded web-config.json')
        except Exception as exc:
            log.info('Could not read web-config.json: {0}'.format(exc))

    site = config.get('site') if isinstance(config.get('site'), dict) else {}
    branding = config.get('branding') if isinstance(config.get('branding'), dict) else {}
    social = config.get('social') if isinstance(config.get('social'), dict) else {}

    manifest = {
        'name': site.get('name', 'My Site'),
        'short_name': site.get('short_name', 'Site'),
        'description': site.get('description', 'A web application'),
        'theme_color': branding.get('theme_color', '#121212'),
        'background_color': branding.get('background_color', '#000000'),
        'display': 'standalone',
        'start_url': '/',
        'scope': '/',
        'orientation': 'any',
        'categories': social.get('categories', ['entertainment']),
        'prefer_related_applications': False,
        'icons': [
            {'src': '/media/icons/favicon-16x16.png', 'sizes': '16x16', 'type': 'image/png', 'purpose': 'any'},
            {'src': '/media/icons/favicon-32x32.png', 'sizes': '32x32', 'type': 'image/png', 'purpose': 'any'},
            {'src': '/media/icons/favicon-96x96.png', 'sizes': '96x96', 'type': 'image/png', 'purpose': 'any'},
            {'src': '/media/icons/web-app-manifest-192x192.png', 'sizes': '192x192', 'type': 'image/png', 'purpose': 'any'},
            {'src': '/media/icons/web-app-manifest-192x192.png', 'sizes': '192x192', 'type': 'image/png', 'purpose': 'maskable'},
            {'src': '/media/icons/web-app-manifest-512x512.png', 'sizes': '512x512', 'type': 'image/png', 'purpose': 'any'},
            {'src': '/media/icons/web-app-manifest-512x512.png', 'sizes': '512x512', 'type': 'image/png', 'purpose': 'maskable'},
        ],
    }

    try:
        payload = json.dumps(manifest, indent=2, ensure_ascii=False)
        if not payload.endswith('\n'):
            payload = payload + '\n'

        if os.path.isfile(MANIFEST_FILE):
            try:
                with open(MANIFEST_FILE, 'r', encoding='utf-8') as handle:
                    existing = handle.read()
            except Exception:
                existing = ''
            if existing.replace('\r\n', '\n') == payload.replace('\r\n', '\n'):
                log.info('site.webmanifest already up to date')
                return True

        with open(MANIFEST_FILE, 'w', encoding='utf-8', newline='\n') as handle:
            handle.write(payload)
        log.info('site.webmanifest written')
        return True
    except Exception as exc:
        log.info('FAILED Could not write manifest: {0}'.format(exc))
        return False
