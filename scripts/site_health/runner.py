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
from paths import LOG_PATH, META_NAME, ROOT_DIR  # noqa: E402
from stopflag import clear_stop, stop_requested  # noqa: E402

try:
    from job_heartbeat import touch_heartbeat, write_job_meta
except Exception:
    def touch_heartbeat(root, stage='', message='', name=META_NAME):
        return {}

    def write_job_meta(root, updates, name=META_NAME, merge=True):
        return {}


def run_check(deep=False):
    deep = bool(deep)
    mode_name = 'check_full' if deep else 'check'
    log.phase(mode_name)
    if deep:
        log.info('Full health check (read-only, cache ignored, deeper probes)')
    else:
        log.info('Quick health check (read-only)')
    touch_heartbeat(
        ROOT_DIR,
        stage=mode_name,
        message='Running {0}...'.format('full triage' if deep else 'triage'),
        name=META_NAME,
    )
    plan = plan_mod.empty_plan()
    plan['mode'] = mode_name
    plan = triage.run_triage(plan, deep=deep)
    if stop_requested():
        log.info('Stop requested during Check.')
        log.result('needs_treatment')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Check stopped', name=META_NAME)
        return 0
    plan = investigate.run_investigate(plan, deep=deep)
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
            sample = finding.get('items_sample') or []
            if sample:
                shown = list(sample)[:12]
                for item in shown:
                    log.info('  - {0}'.format(item))
                extra = int(finding.get('count') or 0) - len(shown)
                if extra > 0:
                    log.info('  - ... {0} more'.format(extra))
        log.result('needs_treatment')
    touch_heartbeat(ROOT_DIR, stage='idle', message='Check finished', name=META_NAME)
    return 0


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
    if stop_requested():
        log.info('Stop requested before treatments.')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Treat stopped', name=META_NAME)
        return 0
    plan = plan_mod.load_plan()
    treatments = plan.get('treatments') if isinstance(plan.get('treatments'), list) else []
    ids = [str(t.get('id') or '') for t in treatments]
    did_register = False

    if 'audio_register_in_place' in ids:
        import treat_audio
        treat_audio.treat_audio_register_in_place()
        did_register = True

    if stop_requested():
        log.info('Stop requested after audio treat.')
        followup.run_followup('treat')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Treat stopped', name=META_NAME)
        return 0

    if 'visual_register_in_place' in ids:
        import treat_visual
        treat_visual.treat_visual_register_in_place()
        did_register = True

    if stop_requested():
        log.info('Stop requested after visual treat.')
        followup.run_followup('treat')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Treat stopped', name=META_NAME)
        return 0

    if 'files_index_rebuild' in ids:
        import files_index
        log.phase('treat:files_index')
        log.info('Rebuilding Files → Audio index from registry...')
        count = files_index.rebuild_audio()
        log.info('INDEX_REBUILT:audio ({0} rows)'.format(count))
        log.treat_result('files_index_rebuild', 'ok', count)

    if stop_requested():
        log.info('Stop requested after Files index treat.')
        followup.run_followup('treat')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Treat stopped', name=META_NAME)
        return 0

    need_delivery = any(
        tid in ids
        for tid in ('listener_delivery', 'audio_delivery', 'visual_delivery')
    )
    # After register-in-place, always rebuild missing/stale deliverables in the
    # same Treat so one "Treat recommended" heals HITZ-style gaps.
    delivery_ok = True
    if need_delivery or did_register:
        import treat_delivery
        delivery_ok = treat_delivery.treat_all_listener_delivery(force=False)
        need_delivery = True

    if stop_requested():
        log.info('Stop requested after delivery treat.')
        followup.run_followup('treat')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Treat stopped', name=META_NAME)
        return 0

    need_playlists = any(tid in ids for tid in ('playlists', 'container_links'))
    if delivery_ok and (need_playlists or need_delivery):
        import treat_playlists
        treat_playlists.treat_playlists()

    if stop_requested():
        log.info('Stop requested after playlists.')
        followup.run_followup('treat')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Treat stopped', name=META_NAME)
        return 0

    need_chrome = any(tid in ids for tid in ('site_chrome', 'container_links'))
    if delivery_ok and (need_chrome or need_delivery):
        import treat_chrome
        treat_chrome.treat_chrome()

    followup.run_followup('treat')
    touch_heartbeat(ROOT_DIR, stage='idle', message='Treat finished', name=META_NAME)
    return 0 if delivery_ok else 1


def run_force():
    log.phase('force')
    log.info('Force full rebuild requested')
    run_check()
    if stop_requested():
        log.info('Stop requested before Force rebuild.')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Force stopped', name=META_NAME)
        return 0
    plan = plan_mod.load_plan()
    findings = plan.get('findings') or []
    critical_catalog = [
        f for f in findings
        if str(f.get('id') or '') in (
            'uncatalogued_audio_masters',
            'uncatalogued_visual_masters',
            'empty_audio_registry_with_disk_masters',
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

    import treat_delivery
    import treat_playlists
    import treat_chrome

    ok = treat_delivery.treat_all_listener_delivery(force=True)
    if stop_requested():
        followup.run_followup('force')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Force stopped', name=META_NAME)
        return 0
    if ok:
        treat_playlists.treat_playlists()
    if stop_requested():
        followup.run_followup('force')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Force stopped', name=META_NAME)
        return 0
    if ok:
        treat_chrome.treat_chrome()

    followup.run_followup('force')
    touch_heartbeat(ROOT_DIR, stage='idle', message='Force finished', name=META_NAME)
    return 0 if ok else 1


def main(argv=None):
    parser = argparse.ArgumentParser(description='bandPromo site health runner')
    parser.add_argument(
        '--mode',
        choices=('check', 'check_full', 'treat', 'force'),
        default='check',
        help='check (quick), check_full (deep read-only), treat, force',
    )
    args = parser.parse_args(argv)

    clear_stop()
    log.begin_run(LOG_PATH)
    write_job_meta(ROOT_DIR, {
        'status': 'running',
        'mode': args.mode,
        'message': 'Starting {0}...'.format(args.mode),
    }, name=META_NAME, merge=False)

    exit_code = 0
    try:
        if args.mode == 'check':
            exit_code = run_check(deep=False)
        elif args.mode == 'check_full':
            exit_code = run_check(deep=True)
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
        log.close_run()

    return exit_code


if __name__ == '__main__':
    sys.exit(main())
