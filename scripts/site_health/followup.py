# -*- coding: utf-8 -*-
"""Re-run triage after treatment."""

from __future__ import print_function

import log
import plan as plan_mod
import triage


def run_followup(previous_mode='treat'):
    log.phase('followup')
    log.info('Re-checking site health after treatment...')
    plan = plan_mod.empty_plan()
    plan['mode'] = 'followup'
    # Treat itself rewrites registry / fingerprints — refresh baseline without
    # flagging that drift as a remaining finding.
    plan = triage.run_triage(plan, suppress_json_drift=True)
    # Import here to avoid circular import at module load in some runners
    import investigate
    plan = investigate.run_investigate(plan)
    plan_mod.overall_from_findings(plan)
    # Drop internal keys
    plan.pop('_registry_status', None)
    plan_mod.save_plan(plan)
    if plan.get('overall') == 'healthy':
        log.info('Follow-up: site looks healthy.')
        log.result('healthy')
    else:
        log.info('Follow-up: findings remain ({0}).'.format(plan.get('overall')))
        log.result('verify_failed')
    return plan
