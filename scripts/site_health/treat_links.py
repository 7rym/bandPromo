# -*- coding: utf-8 -*-
"""
Container / Files-index link treatments.

When site data JSON fingerprints change (campaigns, playlists, galleries,
pages, brands), rebuild Files indexes from the registry so admin lists match
on-disk truth. Playlist payloads and site chrome remain separate treat steps.
"""

from __future__ import print_function

import log

try:
    from stopflag import stop_requested
except Exception:
    def stop_requested():
        return False


def treat_links():
    """Rebuild Files indexes after container JSON drift."""
    log.phase('treat:links')
    log.info('Rebuilding Files indexes from the asset registry (audio + visual).')
    try:
        import files_index
    except Exception as exc:
        log.info('Files index module unavailable: {0}'.format(exc))
        log.treat_result('container_links', 'failed', 0)
        return False

    if stop_requested():
        log.info('Stop requested before Files index rebuild.')
        log.treat_result('container_links', 'stopped', 0)
        return False

    try:
        audio_count = files_index.rebuild_audio()
        log.info('INDEX_REBUILT:audio ({0} rows)'.format(audio_count))
    except Exception as exc:
        log.info('Audio Files index rebuild failed: {0}'.format(exc))
        log.treat_result('container_links', 'failed', 0)
        return False

    if stop_requested():
        log.info('Stop requested after audio Files index rebuild.')
        log.treat_result('container_links', 'stopped', audio_count)
        return False

    try:
        visual_counts = files_index.rebuild_visual()
        total_visual = 0
        for target, count in visual_counts.items():
            log.info('INDEX_REBUILT:{0} ({1} rows)'.format(target, count))
            total_visual += int(count or 0)
    except Exception as exc:
        log.info('Visual Files index rebuild failed: {0}'.format(exc))
        log.treat_result('container_links', 'failed', audio_count)
        return False

    log.info(
        'Container link Treat finished — Files indexes refreshed '
        '({0} audio, {1} visual rows).'.format(audio_count, total_visual)
    )
    log.treat_result('container_links', 'ok', audio_count + total_visual)
    return True
