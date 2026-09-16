# -*- coding: utf-8 -*-
"""
Site health runner — Check / Treat / Force entry point.

Python engine room for System → Status (v0.8).
"""

from __future__ import print_function

import argparse
import os
import sys

# Allow `python scripts/site_health/runner.py` and package-relative imports.
HERE = os.path.dirname(os.path.abspath(__file__))
if HERE not in sys.path:
    sys.path.insert(0, HERE)

SCRIPTS_DIR = os.path.dirname(HERE)
if SCRIPTS_DIR not in sys.path:
    sys.path.insert(0, SCRIPTS_DIR)

try:
    import stdio_utf8
    stdio_utf8.configure()
except Exception:
    pass

import log  # noqa: E402
import plan as plan_mod  # noqa: E402
import triage  # noqa: E402
import investigate  # noqa: E402
import followup  # noqa: E402
from paths import META_NAME, ROOT_DIR, STOP_FLAG  # noqa: E402

try:
    from job_heartbeat import touch_heartbeat, write_job_meta
except Exception:
    def touch_heartbeat(root, stage='', message='', name=META_NAME):
        return {}

    def write_job_meta(root, updates, name=META_NAME, merge=True):
        return {}


def _clear_stop():
    try:
        if os.path.isfile(STOP_FLAG):
            os.remove(STOP_FLAG)
    except Exception:
        pass


def run_check():
    log.phase('check')
    log.info('Site health Check (read-only)')
    touch_heartbeat(ROOT_DIR, stage='check', message='Running triage...', name=META_NAME)
    plan = plan_mod.empty_plan()
    plan['mode'] = 'check'
    plan = triage.run_triage(plan)
    plan = investigate.run_investigate(plan)
    plan_mod.overall_from_findings(plan)
    plan.pop('_registry_status', None)
    plan_mod.save_plan(plan)

    findings = plan.get('findings') or []
    log.phase('diagnose')
    if not findings:
        log.info('Diagnosis: nothing needs treatment. Site looks healthy.')
        log.result('healthy')
    else:
        log.info('Diagnosis: {0} finding(s); overall={1}'.format(
            len(findings), plan.get('overall')
        ))
        for finding in findings:
            log.finding(
                finding.get('severity') or 'attention',
                finding.get('id') or 'finding',
                finding.get('count') or 0,
                finding.get('title') or '',
            )
            body = str(finding.get('body') or '').strip()
            if body:
                log.info('  {0}'.format(body))
        log.result('needs_treatment')
    touch_heartbeat(ROOT_DIR, stage='idle', message='Check finished', name=META_NAME)
    return 0 if plan.get('overall') != 'critical' else 0


def run_treat():
    log.phase('treat')
    log.info('Site health Treat (apply recommended treatments)')
    touch_heartbeat(ROOT_DIR, stage='treat', message='Applying treatments...', name=META_NAME)
    plan = plan_mod.load_plan()
    treatments = plan.get('treatments') if isinstance(plan.get('treatments'), list) else []
    if not treatments:
        log.info('No treatments on the current plan — run Check first.')
        log.result('healthy')
        return 0

    # Always refresh plan with a check first so we treat current truth
    run_check()
    plan = plan_mod.load_plan()
    treatments = plan.get('treatments') if isinstance(plan.get('treatments'), list) else []
    ids = [str(t.get('id') or '') for t in treatments]

    if 'audio_register_in_place' in ids:
        import treat_audio
        treat_audio.treat_audio_register_in_place()

    if 'visual_register_in_place' in ids:
        log.info('Visual register-in-place: not ported yet (next slice).')

    followup.run_followup('treat')
    touch_heartbeat(ROOT_DIR, stage='idle', message='Treat finished', name=META_NAME)
    return 0


def run_force():
    log.phase('force')
    log.info('Force full rebuild requested')
    run_check()
    plan = plan_mod.load_plan()
    findings = plan.get('findings') or []
    critical_catalog = [
        f for f in findings
        if str(f.get('id') or '') in (
            'uncatalogued_audio_masters',
            'registry_missing',
            'registry_unreadable',
        )
    ]
    if critical_catalog:
        log.info('Force blocked: critical catalogue findings must be Treated first.')
        for finding in critical_catalog:
            log.info('  - {0}'.format(finding.get('title') or finding.get('id')))
        log.result('needs_treatment')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Force blocked', name=META_NAME)
        return 0

    log.info('Force delivery rebuild: not ported yet (next slice). Catalogue is clear.')
    log.result('healthy')
    touch_heartbeat(ROOT_DIR, stage='idle', message='Force placeholder finished', name=META_NAME)
    return 0


def main(argv=None):
    parser = argparse.ArgumentParser(description='bandPromo site health runner')
    parser.add_argument(
        '--mode',
        choices=('check', 'treat', 'force'),
        default='check',
        help='check (dry exam), treat (apply plan), force (full delivery rebuild)',
    )
    args = parser.parse_args(argv)

    _clear_stop()
    write_job_meta(ROOT_DIR, {
        'status': 'running',
        'mode': args.mode,
        'message': 'Starting {0}...'.format(args.mode),
    }, name=META_NAME, merge=False)

    exit_code = 0
    try:
        if args.mode == 'check':
            exit_code = run_check()
        elif args.mode == 'treat':
            exit_code = run_treat()
        else:
            exit_code = run_force()
    except Exception as exc:
        log.info('FAILED {0}'.format(exc))
        log.result('failed')
        exit_code = 1
        touch_heartbeat(ROOT_DIR, stage='failed', message=str(exc), name=META_NAME)
    finally:
        write_job_meta(ROOT_DIR, {
            'status': 'idle' if exit_code == 0 else 'failed',
            'exit_code': exit_code,
        }, name=META_NAME, merge=True)

    return exit_code


if __name__ == '__main__':
    sys.exit(main())
