# -*- coding: utf-8 -*-
"""Share / social crop images (site chrome)."""

from __future__ import print_function

import sys

from paths import ROOT_DIR, SCRIPTS_DIR

if SCRIPTS_DIR not in sys.path:
    sys.path.insert(0, SCRIPTS_DIR)

import log


def _load_make_social():
    import makeSocial as ms
    return ms


def generate_share_images():
    """
    Build Facebook/Twitter share crops from Branding poster / share image.
    Returns True on success. Missing source is a soft continue (same as legacy CLI).
    """
    ms = _load_make_social()
    log.info('Generating social share images...')

    config = ms.load_config()
    src_image = ms.resolve_share_image(config)
    created = 0
    kept = 0
    failed = 0

    ms.validate_social_config(config)

    try:
        rel = src_image.relative_to(ms.ROOT_DIR)
        log.info('Share source: {0}'.format(rel.as_posix()))
    except Exception:
        log.info('Share source: {0}'.format(src_image))

    if not src_image.is_file():
        try:
            ms.print_missing_share_image_help(config, src_image)
        except Exception:
            pass
        log.info(
            'Share image missing — continuing without social crops '
            '(fix Branding → Shell media poster).'
        )
        return True

    try:
        from PIL import Image
        with Image.open(src_image) as im:
            src_w, src_h = im.size
        log.info('Share source size: {0}x{1}'.format(src_w, src_h))
    except ImportError:
        log.info('FAILED Pillow is required for share images.')
        return False
    except (OSError, ValueError) as exc:
        try:
            ms.print_missing_share_image_help(config, src_image)
        except Exception:
            pass
        log.info(
            'Could not read share image ({0}) — continuing without social crops.'.format(exc)
        )
        return True

    all_ok = True
    for platform, target_size in ms.PLATFORMS.items():
        result = ms.resize_for_platform(src_image, platform, target_size)
        if result == 'created':
            created += 1
        elif result == 'fresh':
            kept += 1
        else:
            failed += 1
            all_ok = False

    legacy = ms.ROOT_DIR / 'media' / 'share.jpg'
    if legacy.exists():
        log.info('Legacy media/share.jpg still present (safe to delete).')

    try:
        share_rel = ms.SHARE_DIR.relative_to(ms.ROOT_DIR)
    except Exception:
        share_rel = ms.SHARE_DIR
    log.info(
        'Share images: {0} created, {1} kept, {2} failed → {3}/'.format(
            created, kept, failed, share_rel
        )
    )
    return all_ok
