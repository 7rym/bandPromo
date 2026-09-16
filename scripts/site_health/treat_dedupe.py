# -*- coding: utf-8 -*-
"""
Apply duplicate-master treatment: retarget refs to keeper, then remove clones.

Only safe clusters (single campaign/playlist-linked keeper, unreferenced losers).
Conflict clusters are never deleted here.

Scope follows the Review plan:
- duplicate_masters_file → file-hash remaps only (Quick)
- duplicate_masters_content → content demux remaps (Full)
Never run a Full content pass on Apply when the operator only reviewed Quick finds.
"""

from __future__ import print_function

import log
import plan as plan_mod
import registry as reg

try:
    from stopflag import stop_requested
except Exception:
    def stop_requested():
        return False


def _plan_dedupe_scopes(plan):
    """
    Return (include_file, include_content) from findings on the current plan.

    Defaults to file-only when the treatment is listed but finding ids are absent
    (safer than inventing a Full content pass).
    """
    findings = plan.get('findings') if isinstance(plan.get('findings'), list) else []
    ids = set(str(f.get('id') or '').strip() for f in findings if isinstance(f, dict))
    include_file = 'duplicate_masters_file' in ids
    include_content = 'duplicate_masters_content' in ids
    if not include_file and not include_content:
        include_file = True
        include_content = False
    return include_file, include_content


def _collect_safe_removes(registry, include_file=True, include_content=False):
    """Recompute safe clusters for the authorised scopes only."""
    import dedupe

    ref_index = dedupe.build_reference_index(registry)
    remaps = {}  # loser_id → keeper_id

    if include_file:
        log.info('Apply scope: file-hash duplicate clusters (Quick)...')

        def _file_progress(current, total):
            log.progress(
                'dedupe_apply_file_hash',
                current,
                total,
                'hashing same-size masters',
            )
            if current == 1 or current % 25 == 0 or current == total:
                log.info(
                    'Apply file-hash: {0}/{1}'.format(current, total)
                )

        file_clusters = dedupe.find_file_hash_clusters(
            registry, progress_cb=_file_progress
        )
        safe_file, _conflict = dedupe.annotate_clusters(file_clusters, ref_index)
        for cluster in safe_file:
            keeper = cluster.get('keeper_id') or ''
            for loser in cluster.get('remove_ids') or []:
                if loser and keeper and loser != keeper:
                    remaps[loser] = keeper
        log.info(
            'File-hash Apply: {0} safe cluster(s), {1} remap(s) so far.'.format(
                len(safe_file), len(remaps)
            )
        )
    else:
        log.info('Apply scope: skipping file-hash (not on the Review plan).')

    if include_content:
        log.info(
            'Apply scope: content fingerprint clusters (Full) — may take a while...'
        )

        def _progress(current, total):
            log.progress(
                'dedupe_apply_fingerprint', current, total, 'fingerprinting'
            )
            log.info(
                'Apply content fingerprint: {0}/{1}'.format(current, total)
            )

        content_clusters = dedupe.find_content_hash_clusters(
            registry, progress_cb=_progress
        )
        safe_content, _c2 = dedupe.annotate_clusters(content_clusters, ref_index)
        added = 0
        for cluster in safe_content:
            keeper = cluster.get('keeper_id') or ''
            for loser in cluster.get('remove_ids') or []:
        # Prefer content keeper when both scopes map the same loser (override setdefault).
                    if loser and keeper and loser != keeper:
                        if loser not in remaps:
                            added += 1
                        remaps[loser] = keeper
        log.info(
            'Content Apply: {0} safe cluster(s), {1} new remap(s).'.format(
                len(safe_content), added
            )
        )
    else:
        log.info(
            'Apply scope: skipping content fingerprint '
            '(run Full health check first if you want tag-independent clones).'
        )

    import dedupe
    before = len(remaps)
    remaps = dedupe.collapse_remaps(remaps)
    if before != len(remaps):
        log.info(
            'Collapsed remap chains: {0} → {1} loser→keeper edge(s).'.format(
                before, len(remaps)
            )
        )
    return remaps


def treat_dedupe(include_file=None, include_content=None):
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

    if include_file is None or include_content is None:
        plan = plan_mod.load_plan()
        plan_file, plan_content = _plan_dedupe_scopes(plan)
        if include_file is None:
            include_file = plan_file
        if include_content is None:
            include_content = plan_content
    include_file = bool(include_file)
    include_content = bool(include_content)
    log.info(
        'Dedupe Apply authorised scopes: file-hash={0}, content={1}.'.format(
            'yes' if include_file else 'no',
            'yes' if include_content else 'no',
        )
    )

    remaps = _collect_safe_removes(
        registry,
        include_file=include_file,
        include_content=include_content,
    )
    if not remaps:
        log.info('No safe duplicate clusters to remove.')
        log.treat_result('dedupe_retarget_and_remove', 'ok', 0)
        return True

    log.info('Applying {0} loser→keeper remap(s).'.format(len(remaps)))
    for loser, keeper in sorted(remaps.items()):
        log.info('  remove {0} → keep {1}'.format(loser, keeper))

    # Phase 1: retarget every loser to its final keeper (no deletes yet).
    for loser, keeper in sorted(remaps.items()):
        if stop_requested():
            log.info('Stop requested during dedupe retarget.')
            reg.write_registry(registry)
            log.treat_result('dedupe_retarget_and_remove', 'stopped', 0)
            return False
        files_changed = dedupe.retarget_containers(loser, keeper)
        cover_changed = dedupe.retarget_registry_covers(registry, loser, keeper)
        if files_changed or cover_changed:
            log.info(
                '  retargeted {0}→{1}: {2} file(s), {3} cover field(s)'.format(
                    loser, keeper, files_changed, cover_changed
                )
            )

    # Phase 2: delete losers only after all refs point at final keepers.
    removed = 0
    for loser, keeper in sorted(remaps.items()):
        if stop_requested():
            log.info('Stop requested during dedupe remove.')
            reg.write_registry(registry)
            log.treat_result('dedupe_retarget_and_remove', 'stopped', removed)
            return False
        if dedupe.unregister_and_delete_master(registry, loser):
            removed += 1
            log.info('  removed master {0} (kept {1})'.format(loser, keeper))
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
    if include_file and not include_content:
        log.info(
            'Suggestion: run Full health check when convenient to find retagged '
            'or different-size clones (especially audio). That pass is optional '
            'and is not applied until you Review it.'
        )
    log.treat_result('dedupe_retarget_and_remove', 'ok', removed)
    return True
