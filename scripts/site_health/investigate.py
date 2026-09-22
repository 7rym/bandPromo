# -*- coding: utf-8 -*-
"""Deeper investigation for flagged triage findings (still read-only)."""

from __future__ import print_function

import os

import log
import plan as plan_mod
import registry as reg


def _deep_probe_clean(registry, status):
    """Full-check probes when triage found nothing — still read-only."""
    log.info('Deep probe: re-checking registry vs disk and delivery completeness...')
    if status != 'ok':
        log.info('Deep probe: registry not ok ({0}) — nothing further.'.format(status))
        return
    pending_audio = reg.uncatalogued_audio_masters(registry)
    pending_visual = reg.uncatalogued_visual_masters(registry)
    pending_sfx = reg.uncatalogued_sfx_masters(registry)
    missing_audio = reg.missing_audio_deliverables(registry)
    missing_visual = reg.missing_visual_deliveries(registry)
    missing_sfx = reg.missing_sfx_deliverables(registry)
    disk = reg.list_audio_masters_on_disk()
    non_ast = [n for n in disk if not reg.is_asset_id(os.path.splitext(n)[0])]
    if pending_audio:
        log.items('Deep probe uncatalogued audio', pending_audio)
    if pending_visual:
        log.items('Deep probe uncatalogued visual', pending_visual)
    if pending_sfx:
        log.items('Deep probe uncatalogued sound effects', pending_sfx)
    if missing_audio:
        log.items('Deep probe missing audio delivery', missing_audio)
    if missing_visual:
        log.items('Deep probe missing visual delivery', missing_visual)
    if missing_sfx:
        log.items('Deep probe missing sound-effect delivery', missing_sfx)
    if non_ast:
        log.items('Deep probe non-ast_* audio masters', non_ast)
    if not (
        pending_audio or pending_visual or pending_sfx
        or missing_audio or missing_visual or missing_sfx or non_ast
    ):
        log.info('Deep probe clean — registry, disk, and delivery existence agree.')


def _add_content_dedupe_findings(plan, registry):
    """Full check: content fingerprints (demux / RGB) ignoring tags."""
    try:
        import dedupe
    except Exception as exc:
        log.info('Content dedupe module unavailable: {0}'.format(exc))
        return

    if not dedupe.xxhash_available():
        log.info(
            'Content fingerprint probe skipped: xxhash not importable '
            '(scripts/vendor bootstrap missing?).'
        )
        existing = set(
            str(f.get('id') or '')
            for f in (plan.get('findings') or [])
            if isinstance(f, dict)
        )
        if 'dedupe_unavailable' not in existing:
            plan_mod.add_finding(
                plan, 'dedupe_unavailable', 'attention',
                'Duplicate master check could not run', 1,
                '',
                body=(
                    'A helper library Site health needs for duplicate checks is missing on this host. '
                    'Ask a developer to repair the scripts vendor bundle.'
                ),
            )
        return

    log.info(
        'Deep probe: content fingerprints for duplicate masters '
        '(audio full demux-hash; video demux-copy by duration; still RGB by dimensions)...'
    )

    def _progress(current, total):
        log.progress(
            'dedupe_content',
            current,
            total,
            'fingerprinting masters',
        )
        log.info(
            'Content fingerprinting: {0}/{1}'.format(current, total)
        )
        try:
            from job_heartbeat import touch_heartbeat
            from paths import META_NAME, ROOT_DIR
            touch_heartbeat(
                ROOT_DIR,
                stage='check',
                message='Content fingerprint {0}/{1}'.format(current, total),
                name=META_NAME,
            )
        except Exception:
            pass

    clusters = dedupe.find_content_hash_clusters(registry, progress_cb=_progress)
    stats = getattr(dedupe.find_content_hash_clusters, 'last_stats', {}) or {}
    log.info(
        'Content scan: {0} candidates; audio stream-groups={1} '
        '(fingerprinted {2}); video groups={3} (skipped no-duration {4}); '
        'still dim-groups={5}; demux attempts={6}; stills hashed={7}.'.format(
            stats.get('candidates', 0),
            stats.get('audio_multi_stream_groups', 0),
            stats.get('audio_fingerprinted', 0),
            stats.get('video_multi_duration_groups', 0),
            stats.get('video_skipped_no_duration', 0),
            stats.get('still_multi_dim_groups', 0),
            stats.get('demuxed', 0),
            stats.get('stills_hashed', 0),
        )
    )
    ref_index = dedupe.build_reference_index(registry)
    safe, conflict = dedupe.annotate_clusters(clusters, ref_index)
    remove_count = sum(len(c.get('remove_ids') or []) for c in safe)

    # Avoid stacking identical conflict findings already raised by Quick file-hash.
    existing_ids = set(
        str(f.get('id') or '')
        for f in (plan.get('findings') or [])
        if isinstance(f, dict)
    )

    if safe:
        plan_mod.add_finding(
            plan, 'duplicate_masters_content', 'attention',
            'Duplicate masters with identical audio/image content', remove_count,
            'dedupe_retarget_and_remove',
            sample=dedupe.cluster_sample_lines(safe),
            body=(
                '{0} group(s) of files sound or look the same (tags ignored). '
                'Apply keeps the campaign-linked copy and removes {1} unused clone(s).'
            ).format(len(safe), remove_count),
        )
        log.items(
            'Duplicate content clusters (safe to remove)',
            dedupe.cluster_sample_lines(safe, limit=20),
        )
    if conflict and 'duplicate_masters_conflict' not in existing_ids:
        plan_mod.add_finding(
            plan, 'duplicate_masters_conflict', 'attention',
            'Duplicate masters both linked to campaigns', len(conflict),
            '',
            sample=dedupe.cluster_sample_lines(conflict),
            body=(
                '{0} content-identical cluster(s) have more than one campaign/playlist '
                'link — not auto-removed.'
            ).format(len(conflict)),
        )
        log.items(
            'Duplicate content conflicts (manual)',
            dedupe.cluster_sample_lines(conflict, limit=20),
        )
    if not safe and not conflict:
        log.info('Deep probe: no content-identical duplicate masters found.')


def run_investigate(plan, deep=False):
    findings = plan.get('findings') if isinstance(plan.get('findings'), list) else []
    deep = bool(deep)

    log.phase('investigate')
    registry, status = reg.load_registry()
    if status != 'ok' and status != 'missing':
        log.info('Registry not usable for investigation ({0}).'.format(status))
        return plan

    if not findings:
        if deep:
            _deep_probe_clean(registry, status)
        else:
            log.info('Nothing flagged in triage — skipping deeper investigation.')
    else:
        for finding in findings:
            fid = str(finding.get('id') or '')
            if fid == 'uncatalogued_audio_masters':
                if status == 'missing':
                    pending = [{
                        'master_filename': name,
                        'asset_id': name.rsplit('.', 1)[0],
                    } for name in reg.list_audio_masters_on_disk()
                        if reg.is_asset_id(name.rsplit('.', 1)[0])]
                else:
                    pending = reg.uncatalogued_audio_masters(registry)
                finding['items_sample'] = [p['master_filename'] for p in pending[:12]]
                finding['count'] = len(pending)
                log.items('Investigate audio masters (uncatalogued)', pending)
            elif fid == 'uncatalogued_visual_masters':
                pending = reg.uncatalogued_visual_masters(registry) if status == 'ok' else []
                finding['items_sample'] = [p['master_filename'] for p in pending[:12]]
                finding['count'] = len(pending)
                log.items('Investigate visual masters (uncatalogued)', pending)
            elif fid == 'uncatalogued_sfx_masters':
                pending = reg.uncatalogued_sfx_masters(registry) if status == 'ok' else []
                finding['items_sample'] = [p['master_filename'] for p in pending[:12]]
                finding['count'] = len(pending)
                log.items('Investigate sound-effect masters (uncatalogued)', pending)
            elif fid == 'empty_audio_registry_with_disk_masters':
                disk = reg.list_audio_masters_on_disk()
                finding['items_sample'] = disk[:12]
                finding['count'] = len(disk)
                log.items('Investigate empty audio registry — disk masters', disk)
            elif fid == 'audio_display_missing_tags':
                try:
                    import audio_display
                    pending = audio_display.incomplete_audio_masters(registry) if status == 'ok' else []
                except Exception:
                    pending = []
                finding['items_sample'] = [p.get('master_filename') for p in pending[:12]]
                finding['count'] = len(pending)
                log.items('Investigate audio display (missing tags)', pending)
            elif fid == 'audio_embedded_cover_unextracted':
                try:
                    import audio_covers
                    pending = audio_covers.pending_cover_extract(registry) if status == 'ok' else []
                except Exception:
                    pending = []
                finding['items_sample'] = [p.get('master_filename') for p in pending[:12]]
                finding['count'] = len(pending)
                log.items('Investigate audio covers (embedded, unlinked)', pending)
            elif fid == 'media_janitor_orphans':
                try:
                    import janitor
                    targets = janitor.probe_janitor_targets(registry) if status in ('ok', 'missing') else []
                except Exception:
                    targets = []
                finding['items_sample'] = [t.get('path') for t in targets[:12]]
                finding['count'] = len(targets)
                log.items('Investigate media janitor targets', [t.get('path') for t in targets])
            elif fid == 'data_janitor_ephemeral':
                try:
                    import data_janitor
                    targets = data_janitor.probe_ephemeral_targets()
                except Exception:
                    targets = []
                finding['items_sample'] = [t.get('path') for t in targets[:12]]
                finding['count'] = len(targets)
                log.items('Investigate data janitor leftovers', [t.get('path') for t in targets])
            elif fid == 'storage_package_scratch':
                try:
                    import storage_reclaim
                    targets = storage_reclaim.probe_package_scratch()
                except Exception:
                    targets = []
                finding['items_sample'] = [t.get('path') for t in targets[:12]]
                finding['count'] = len(targets)
                log.items(
                    'Investigate package scratch',
                    [
                        '{0} ({1})'.format(t.get('path'), t.get('size_label') or '')
                        for t in targets
                    ],
                )
            elif fid == 'storage_ready_archives':
                try:
                    import storage_reclaim
                    probe = storage_reclaim.probe_ready_archives()
                except Exception:
                    probe = {'removable': [], 'kept': []}
                removable = probe.get('removable') or []
                kept = probe.get('kept') or []
                finding['items_sample'] = [
                    '{0}/{1} ({2})'.format(
                        r.get('kind_label'), r.get('job_id'), r.get('size_label') or '',
                    )
                    for r in removable[:12]
                ]
                finding['count'] = len(removable)
                log.items(
                    'Investigate older Ready archives',
                    finding['items_sample'],
                )
                if kept:
                    log.items(
                        'Keeping newest Ready archive per kind',
                        [
                            '{0}/{1} ({2})'.format(
                                k.get('kind_label'), k.get('job_id'), k.get('size_label') or '',
                            )
                            for k in kept
                        ],
                    )
            elif fid == 'data_container_unlinked':
                try:
                    import data_janitor
                    probe = data_janitor.probe_containers()
                except Exception:
                    probe = {'relinkable': [], 'registry_stubs': []}
                rows = (probe.get('relinkable') or []) + (probe.get('registry_stubs') or [])
                finding['items_sample'] = [
                    '{0}/{1}'.format(r.get('kind'), r.get('id'))
                    for r in rows[:12]
                ]
                finding['count'] = len(rows)
                log.items(
                    'Investigate container registry fixes',
                    [
                        '{0}/{1} → {2}'.format(
                            r.get('kind'), r.get('id'),
                            r.get('campaign_id') or '(drop stub)',
                        )
                        for r in rows
                    ],
                )
            elif fid == 'data_container_orphans':
                try:
                    import data_janitor
                    probe = data_janitor.probe_containers()
                except Exception:
                    probe = {'manual': []}
                rows = probe.get('manual') or []
                sample_rows = []
                log_lines = []
                for row in rows[:12]:
                    formatted = data_janitor.format_manual_sample_row(row)
                    if formatted:
                        sample_rows.append(formatted)
                    line = data_janitor.format_manual_log_line(row)
                    if line:
                        log_lines.append(line)
                finding['items_sample'] = sample_rows
                finding['count'] = len(rows)
                finding['body'] = (
                    '{0} playlist/gallery/page item(s) are orphaned or unowned. '
                    'Adopt them into a campaign, or Delete leftovers — Site health will not guess.'
                ).format(len(rows))
                log.items('Investigate data container orphans', log_lines)
            elif fid in ('orphan_assets_in_containers', 'orphan_assets_multi_campaign'):
                try:
                    import orphan_homes
                    probe = orphan_homes.probe_orphan_homes(registry) if status == 'ok' else {
                        'stampable': [], 'ambiguous': [],
                    }
                except Exception:
                    probe = {'stampable': [], 'ambiguous': []}
                if fid == 'orphan_assets_in_containers':
                    rows = probe.get('stampable') or []
                    finding['items_sample'] = [
                        '{0} → {1}'.format(
                            r.get('filename') or r.get('asset_id'),
                            r.get('campaign_id'),
                        )
                        for r in rows[:12]
                    ]
                    finding['count'] = len(rows)
                    log.items('Investigate orphan homes (stampable)', finding['items_sample'])
                else:
                    rows = probe.get('ambiguous') or []
                    sample_rows = []
                    log_lines = []
                    for row in rows[:12]:
                        formatted = orphan_homes.format_ambiguous_sample_row(row)
                        if formatted:
                            sample_rows.append(formatted)
                        line = orphan_homes.format_ambiguous_log_line(row)
                        if line:
                            log_lines.append(line)
                    finding['items_sample'] = sample_rows
                    finding['count'] = len(rows)
                    finding['body'] = (
                        '{0} file(s) are used by more than one campaign and have no catalogue home. '
                        'Choose which campaign should own each file below — Site health will not guess.'
                    ).format(len(rows))
                    log.items('Investigate orphan homes (multi-campaign)', log_lines)
            elif fid == 'empty_sfx_registry_with_disk_masters':
                disk = reg.list_sfx_masters_on_disk()
                finding['items_sample'] = disk[:12]
                finding['count'] = len(disk)
                log.items('Investigate empty SFX registry — disk masters', disk)
            elif fid == 'non_ast_audio_masters':
                sample = finding.get('items_sample') or []
                log.items('Investigate non-ast_* audio masters', sample)
            elif fid == 'files_index_audio_undercount':
                log.info(
                    'Investigate Files index undercount: {0} missing row(s) vs registry'.format(
                        finding.get('count') or 0
                    )
                )
            elif fid in (
                'duplicate_masters_file',
                'duplicate_masters_content',
                'duplicate_masters_conflict',
            ):
                sample = finding.get('items_sample') or []
                log.items('Investigate {0}'.format(fid), sample)
            else:
                sample = finding.get('items_sample') or []
                if sample:
                    log.items('Investigate {0}'.format(fid), sample)

        if deep:
            # Always re-list delivery gaps even when other findings dominate.
            if status == 'ok':
                missing_audio = reg.missing_audio_deliverables(registry)
                missing_visual = reg.missing_visual_deliveries(registry)
                if missing_audio:
                    log.items('Deep probe missing audio delivery', missing_audio)
                if missing_visual:
                    log.items('Deep probe missing visual delivery', missing_visual)

    # Full check always runs content fingerprinting (expensive).
    if deep and status == 'ok':
        _add_content_dedupe_findings(plan, registry)

    return plan
