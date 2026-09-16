# -*- coding: utf-8 -*-
"""Deeper investigation for flagged triage findings (still read-only)."""

from __future__ import print_function

import log
import registry as reg


def run_investigate(plan):
    findings = plan.get('findings') if isinstance(plan.get('findings'), list) else []
    if not findings:
        log.phase('investigate')
        log.info('Nothing flagged in triage — skipping deeper investigation.')
        return plan

    log.phase('investigate')
    registry, status = reg.load_registry()
    if status != 'ok' and status != 'missing':
        log.info('Registry not usable for investigation ({0}).'.format(status))
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
            log.info('Investigate audio masters: {0} uncatalogued'.format(len(pending)))
            if pending:
                for item in pending[:8]:
                    log.info('  - {0}'.format(item['master_filename']))
                if len(pending) > 8:
                    log.info('  - ... {0} more'.format(len(pending) - 8))
        elif fid == 'uncatalogued_visual_masters':
            pending = reg.uncatalogued_visual_masters(registry) if status == 'ok' else []
            finding['items_sample'] = [p['master_filename'] for p in pending[:12]]
            finding['count'] = len(pending)
            log.info('Investigate visual masters: {0} uncatalogued'.format(len(pending)))

    return plan
