# -*- coding: utf-8 -*-
"""
Apply duplicate-master treatment: retarget refs to keeper, then remove clones.

Only safe clusters (single campaign/playlist-linked keeper, unreferenced losers).
Conflict clusters are never deleted here.
"""

from __future__ import print_function

import log
import registry as reg

try:
    from stopflag import stop_requested
except Exception:
    def stop_requested():
        return False


def _collect_safe_removes(registry):
    """Recompute file + content safe clusters; merge unique remove→keeper maps."""
    import dedupe

    ref_index = dedupe.build_reference_index(registry)
    remaps = {}  # loser_id → keeper_id

    file_clusters = dedupe.find_file_hash_clusters(registry)
    safe_file, _conflict = dedupe.annotate_clusters(file_clusters, ref_index)
    for cluster in safe_file:
        keeper = cluster.get('keeper_id') or ''
        for loser in cluster.get('remove_ids') or []:
            if loser and keeper and loser != keeper:
                remaps[loser] = keeper

    # Content clusters may find more (different tags/sizes).
    log.info('Content fingerprint pass for Apply (may take a while)...')

    def _progress(current, total):
        log.progress('dedupe_apply_fingerprint', current, total, 'fingerprinting')

    content_clusters = dedupe.find_content_hash_clusters(
        registry, progress_cb=_progress
    )
    safe_content, _c2 = dedupe.annotate_clusters(content_clusters, ref_index)
    for cluster in safe_content:
        keeper = cluster.get('keeper_id') or ''
        for loser in cluster.get('remove_ids') or []:
            if loser and keeper and loser != keeper:
                # Prefer existing remap if already from file-hash.
                remaps.setdefault(loser, keeper)

    return remaps


def treat_dedupe():
    log.phase('treat:dedupe')
    log.info('Retargeting duplicate masters and removing unreferenced clones.')

    registry, status = reg.load_registry()
    if status != 'ok':
        log.info('Registry not usable ({0}).'.format(status))
        log.treat_result('dedupe_retarget_and_remove', 'failed', 0)
        return False

    try:
        import dedupe
    except Exception as exc:
        log.info('Dedupe module unavailable: {0}'.format(exc))
        log.treat_result('dedupe_retarget_and_remove', 'failed', 0)
        return False

    if stop_requested():
        log.treat_result('dedupe_retarget_and_remove', 'stopped', 0)
        return False

    remaps = _collect_safe_removes(registry)
    if not remaps:
        log.info('No safe duplicate clusters to remove.')
        log.treat_result('dedupe_retarget_and_remove', 'ok', 0)
        return True

    log.info('Applying {0} loser→keeper remap(s).'.format(len(remaps)))
    for loser, keeper in sorted(remaps.items()):
        log.info('  remove {0} → keep {1}'.format(loser, keeper))

    removed = 0
    for loser, keeper in sorted(remaps.items()):
        if stop_requested():
            log.info('Stop requested during dedupe Apply.')
            reg.write_registry(registry)
            log.treat_result('dedupe_retarget_and_remove', 'stopped', removed)
            return False

        # Retarget containers then registry covers, then delete loser.
        files_changed = dedupe.retarget_containers(loser, keeper)
        cover_changed = dedupe.retarget_registry_covers(registry, loser, keeper)
        if files_changed or cover_changed:
            log.info(
                '  retargeted {0}→{1}: {2} file(s), {3} cover field(s)'.format(
                    loser, keeper, files_changed, cover_changed
                )
            )
        if dedupe.unregister_and_delete_master(registry, loser):
            removed += 1
            log.info('  removed master {0}'.format(loser))
        else:
            log.info('  could not unregister {0} (already gone?)'.format(loser))

    reg.write_registry(registry)

    try:
        import files_index
        files_index.rebuild_audio()
        files_index.rebuild_visual()
        log.info('Files indexes rebuilt after dedupe.')
    except Exception as exc:
        log.info('Files index rebuild after dedupe failed: {0}'.format(exc))

    log.info('Dedupe Apply finished: removed {0} clone(s).'.format(removed))
    log.treat_result('dedupe_retarget_and_remove', 'ok', removed)
    return True
