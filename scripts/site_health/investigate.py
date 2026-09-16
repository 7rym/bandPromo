# -*- coding: utf-8 -*-
"""Deeper investigation for flagged triage findings (still read-only)."""

from __future__ import print_function

import os

import log
import registry as reg


def _deep_probe_clean(registry, status):
    """Full-check probes when triage found nothing — still read-only."""
    log.info('Deep probe: re-checking registry vs disk and delivery completeness...')
    if status != 'ok':
        log.info('Deep probe: registry not ok ({0}) — nothing further.'.format(status))
        return
    pending_audio = reg.uncatalogued_audio_masters(registry)
    pending_visual = reg.uncatalogued_visual_masters(registry)
    missing_audio = reg.missing_audio_deliverables(registry)
    missing_visual = reg.missing_visual_deliveries(registry)
    disk = reg.list_audio_masters_on_disk()
    non_ast = [n for n in disk if not reg.is_asset_id(os.path.splitext(n)[0])]
    if pending_audio:
        log.items('Deep probe uncatalogued audio', pending_audio)
    if pending_visual:
        log.items('Deep probe uncatalogued visual', pending_visual)
    if missing_audio:
        log.items('Deep probe missing audio delivery', missing_audio)
    if missing_visual:
        log.items('Deep probe missing visual delivery', missing_visual)
    if non_ast:
        log.items('Deep probe non-ast_* audio masters', non_ast)
    if not (pending_audio or pending_visual or missing_audio or missing_visual or non_ast):
        log.info('Deep probe clean — registry, disk, and delivery existence agree.')


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
        return plan

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
        elif fid == 'empty_audio_registry_with_disk_masters':
            disk = reg.list_audio_masters_on_disk()
            finding['items_sample'] = disk[:12]
            finding['count'] = len(disk)
            log.items('Investigate empty audio registry — disk masters', disk)
        elif fid == 'non_ast_audio_masters':
            sample = finding.get('items_sample') or []
            log.items('Investigate non-ast_* audio masters', sample)
        elif fid == 'files_index_audio_undercount':
            log.info(
                'Investigate Files index undercount: {0} missing row(s) vs registry'.format(
                    finding.get('count') or 0
                )
            )
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

    return plan
