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
    import bandpromo_python_path
    bandpromo_python_path.ensure_vendor_on_sys_path()
except Exception:
    pass

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


def _site_banner():
    """First Activity line: which install this job is running on + app version."""
    import json

    config_path = os.path.join(ROOT_DIR, 'web-config.json')
    name = ''
    url = ''
    try:
        with open(config_path, 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
        site = payload.get('site') if isinstance(payload, dict) else None
        if isinstance(site, dict):
            name = str(site.get('name') or '').strip()
            url = str(site.get('url') or '').strip()
    except Exception:
        pass
    host = ''
    if url:
        text = url
        for prefix in ('https://', 'http://'):
            if text.lower().startswith(prefix):
                text = text[len(prefix):]
                break
        host = text.split('/')[0].strip()
    if name and host:
        base = 'Site health running on {0} ({1})'.format(name, host)
    elif name:
        base = 'Site health running on {0}'.format(name)
    elif host:
        base = 'Site health running on {0}'.format(host)
    else:
        base = 'Site health running on this install'
    version = plan_mod.read_app_version()
    if version:
        return '{0} (running bandPromo {1})'.format(base, version)
    return base


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
    # Sticky rebuild failures (older stream may still look Ready on disk).
    try:
        import delivery_failures
        delivery_failures.apply_to_plan(plan)
    except Exception:
        pass
    plan_mod.overall_from_findings(plan)
    import summary as summary_mod
    summary_mod.build_summary(plan)
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
    log.info('Site health Treat (apply selected treatments)')
    touch_heartbeat(ROOT_DIR, stage='treat', message='Applying treatments...', name=META_NAME)
    plan = plan_mod.load_plan()
    treatments = plan.get('treatments') if isinstance(plan.get('treatments'), list) else []
    if not treatments:
        log.info('No treatments on the current plan — run Check first.')
        log.result('healthy')
        return 0

    import treat_selection
    selected_ids = treat_selection.load_selection()

    # Capture Review-authorised dedupe scopes before the refresh Check rewrites the plan.
    # Quick refresh must not unlock a silent Full content delete, and must not drop a
    # Full-authorised content scope the operator already Review'd.
    import treat_dedupe as treat_dedupe_mod
    dedupe_file_scope, dedupe_content_scope = treat_dedupe_mod._plan_dedupe_scopes(plan)
    if selected_ids is not None and 'dedupe_retarget_and_remove' not in selected_ids:
        dedupe_file_scope = False
        dedupe_content_scope = False

    # Always Quick-refresh for current register/delivery truth. Dedupe Apply uses the
    # captured Review scopes (file and/or content) — never invent a Full pass here.
    run_check(deep=False)
    if stop_requested():
        log.info('Stop requested before treatments.')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Treat stopped', name=META_NAME)
        return 0
    plan = plan_mod.load_plan()
    treatments = plan.get('treatments') if isinstance(plan.get('treatments'), list) else []
    ids = [str(t.get('id') or '') for t in treatments if str(t.get('id') or '')]
    if selected_ids is not None:
        ids = [tid for tid in ids if tid in selected_ids]
        log.info(
            'Applying {0} selected treatment(s): {1}'.format(
                len(ids),
                ', '.join(ids) if ids else '(none)',
            )
        )
        if not ids and not (dedupe_file_scope or dedupe_content_scope):
            log.info('Nothing selected to apply.')
            treat_selection.clear_selection()
            log.result('healthy')
            followup.run_followup('treat')
            touch_heartbeat(ROOT_DIR, stage='idle', message='Treat finished', name=META_NAME)
            return 0
    # Keep dedupe on the treat list when Review authorised it, even if Quick refresh
    # cleared file-hash findings (content-only Full plans).
    if (
        (dedupe_file_scope or dedupe_content_scope)
        and 'dedupe_retarget_and_remove' not in ids
    ):
        ids.append('dedupe_retarget_and_remove')
    did_register = False
    treat_ok = True

    # Plan order: audio → visual → sfx → dedupe → delivery → janitor → links → playlists → chrome
    need_audio = any(
        tid in ids
        for tid in (
            'audio_register_in_place',
            'audio_fill_display_from_tags',
            'audio_extract_covers',
        )
    )
    if need_audio:
        import treat_audio
        # Register + tag fill when either of those treatments is planned, or when
        # cover extract is alone but masters still need a registry row first.
        if any(
            tid in ids
            for tid in ('audio_register_in_place', 'audio_fill_display_from_tags')
        ):
            treat_audio.treat_audio_register_in_place()
            did_register = True
        if 'audio_extract_covers' in ids:
            cover_fixed, cover_failed = treat_audio.treat_audio_extract_covers()
            if cover_fixed > 0:
                did_register = True
            if cover_failed > 0:
                treat_ok = False

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

    if 'sfx_register_in_place' in ids:
        import treat_sfx
        treat_sfx.treat_sfx_register_in_place()
        did_register = True

    if stop_requested():
        log.info('Stop requested after sfx register treat.')
        followup.run_followup('treat')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Treat stopped', name=META_NAME)
        return 0

    if 'data_container_relink' in ids:
        import treat_data_containers
        _fixed, relink_failed = treat_data_containers.treat_data_container_relink()
        if relink_failed > 0:
            treat_ok = False

    if stop_requested():
        log.info('Stop requested after data container relink.')
        followup.run_followup('treat')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Treat stopped', name=META_NAME)
        return 0

    if 'orphan_home_stamp' in ids:
        import orphan_homes
        _fixed, orphan_failed = orphan_homes.treat_orphan_homes()
        if orphan_failed > 0:
            treat_ok = False
        if _fixed > 0:
            did_register = True

    if stop_requested():
        log.info('Stop requested after orphan home stamp.')
        followup.run_followup('treat')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Treat stopped', name=META_NAME)
        return 0

    if 'dedupe_retarget_and_remove' in ids:
        treat_ok = treat_dedupe_mod.treat_dedupe(
            include_file=dedupe_file_scope,
            include_content=dedupe_content_scope,
        ) and treat_ok

    if stop_requested():
        log.info('Stop requested after dedupe treat.')
        followup.run_followup('treat')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Treat stopped', name=META_NAME)
        return 0

    need_sfx = 'sfx_delivery' in ids
    need_delivery = any(
        tid in ids
        for tid in ('listener_delivery', 'audio_delivery', 'visual_delivery')
    )
    # After register-in-place, rebuild missing/stale deliverables in the same Treat.
    if need_delivery or did_register:
        import treat_delivery
        treat_ok = treat_delivery.treat_all_listener_delivery(force=False)
        need_delivery = True
        need_sfx = False  # already chained inside treat_all_listener_delivery
    elif need_sfx:
        import treat_sfx
        treat_ok = treat_sfx.treat_sfx(force=False)

    if stop_requested():
        log.info('Stop requested after delivery/sfx treat.')
        followup.run_followup('treat')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Treat stopped', name=META_NAME)
        return 0

    if 'media_janitor_prune' in ids:
        import treat_janitor
        _removed, janitor_failed = treat_janitor.treat_media_janitor_prune()
        if janitor_failed > 0:
            treat_ok = False

    if stop_requested():
        log.info('Stop requested after media janitor.')
        followup.run_followup('treat')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Treat stopped', name=META_NAME)
        return 0

    if 'data_janitor_prune' in ids:
        import treat_data_janitor
        _removed, data_janitor_failed = treat_data_janitor.treat_data_janitor_prune()
        if data_janitor_failed > 0:
            treat_ok = False

    if stop_requested():
        log.info('Stop requested after data janitor.')
        followup.run_followup('treat')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Treat stopped', name=META_NAME)
        return 0

    if 'storage_package_prune' in ids:
        import treat_storage_reclaim
        _removed, storage_pkg_failed = treat_storage_reclaim.treat_storage_package_prune()
        if storage_pkg_failed > 0:
            treat_ok = False

    if stop_requested():
        log.info('Stop requested after package scratch prune.')
        followup.run_followup('treat')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Treat stopped', name=META_NAME)
        return 0

    if 'storage_archives_prune' in ids:
        import treat_storage_reclaim
        _removed, storage_arc_failed = treat_storage_reclaim.treat_storage_archives_prune()
        if storage_arc_failed > 0:
            treat_ok = False

    if stop_requested():
        log.info('Stop requested after Ready archive prune.')
        followup.run_followup('treat')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Treat stopped', name=META_NAME)
        return 0

    need_links = any(tid in ids for tid in ('container_links', 'files_index_rebuild'))
    if treat_ok and (need_links or did_register):
        import treat_links
        treat_ok = treat_links.treat_links() and treat_ok

    if stop_requested():
        log.info('Stop requested after links treat.')
        followup.run_followup('treat')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Treat stopped', name=META_NAME)
        return 0

    need_playlists = any(tid in ids for tid in ('playlists', 'container_links'))
    if treat_ok and (need_playlists or need_delivery):
        import treat_playlists
        treat_ok = treat_playlists.treat_playlists() and treat_ok

    if stop_requested():
        log.info('Stop requested after playlists.')
        followup.run_followup('treat')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Treat stopped', name=META_NAME)
        return 0

    need_chrome = any(tid in ids for tid in ('site_chrome', 'container_links'))
    if treat_ok and (need_chrome or need_delivery):
        import treat_chrome
        treat_ok = treat_chrome.treat_chrome() and treat_ok

    followup.run_followup('treat')
    try:
        import treat_selection
        treat_selection.clear_selection()
    except Exception:
        pass
    touch_heartbeat(ROOT_DIR, stage='idle', message='Treat finished', name=META_NAME)
    return 0 if treat_ok else 1


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
            'uncatalogued_sfx_masters',
            'empty_audio_registry_with_disk_masters',
            'empty_sfx_registry_with_disk_masters',
            'registry_missing',
            'registry_unreadable',
        )
    ]
    if critical_catalog:
        log.info('Force unavailable: serious catalogue problems must be fixed with Review → Apply first.')
        for finding in critical_catalog:
            log.info('  - {0}'.format(finding.get('title') or finding.get('id')))
        log.result('needs_treatment')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Force blocked', name=META_NAME)
        return 0

    import treat_delivery
    import treat_links
    import treat_playlists
    import treat_chrome

    ok = treat_delivery.treat_all_listener_delivery(force=True)
    if stop_requested():
        followup.run_followup('force')
        touch_heartbeat(ROOT_DIR, stage='idle', message='Force stopped', name=META_NAME)
        return 0
    if ok:
        treat_links.treat_links()
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
    log.info(_site_banner())
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
        # Closed stdout after double-wrap must not end Treat as a bare opaque failure.
        if 'closed file' in str(exc).lower():
            try:
                import stdio_utf8
                stdio_utf8.repair()
            except Exception:
                pass
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
