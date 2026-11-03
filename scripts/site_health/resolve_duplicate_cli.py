# -*- coding: utf-8 -*-
"""
One-shot operator resolve for a Site health duplicate conflict cluster.

Usage (from install root):
  python -u scripts/site_health/resolve_duplicate_cli.py path/to/choices.json

choices.json:
  {"keeper_id": "ast_…", "loser_ids": ["ast_…", …]}

Prints one JSON line: {"ok": true, "removed": N} or {"ok": false, "error": "…"}.
"""

from __future__ import print_function

import json
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
SCRIPTS = os.path.dirname(HERE)
if SCRIPTS not in sys.path:
    sys.path.insert(0, SCRIPTS)
if HERE not in sys.path:
    sys.path.insert(0, HERE)

try:
    import stdio_utf8
    stdio_utf8.configure()
except Exception:
    pass


def _fail(message):
    print(json.dumps({'ok': False, 'error': str(message)}, ensure_ascii=False))
    return 1


def main(argv):
    if len(argv) < 2:
        return _fail('Missing choices JSON path.')
    path = os.path.abspath(str(argv[1] or '').strip())
    if path == '' or not os.path.isfile(path):
        return _fail('Choices file not found.')

    try:
        with open(path, 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
    except Exception as exc:
        return _fail('Could not read choices: {0}'.format(exc))

    if not isinstance(payload, dict):
        return _fail('Choices must be a JSON object.')

    keeper = str(payload.get('keeper_id') or '').strip()
    losers_raw = payload.get('loser_ids') or []
    if not isinstance(losers_raw, list):
        losers_raw = []
    losers = []
    for item in losers_raw:
        aid = str(item or '').strip()
        if aid and aid != keeper and aid not in losers:
            losers.append(aid)

    if not keeper.startswith('ast_'):
        return _fail('Keeper must be an asset id.')
    if not losers:
        return _fail('Need at least one other copy to fold into the keeper.')

    import registry as reg
    import dedupe

    registry, status = reg.load_registry()
    if status != 'ok':
        return _fail('Registry not usable ({0}).'.format(status))

    assets = registry.get('assets') if isinstance(registry.get('assets'), dict) else {}
    if keeper not in assets:
        return _fail('Keeper asset is not in the catalogue.')
    for loser in losers:
        if loser not in assets:
            return _fail('Copy {0} is not in the catalogue.'.format(loser))

    remaps = {loser: keeper for loser in losers}
    removed, err = dedupe.apply_operator_remaps(registry, remaps)
    if err:
        return _fail(err)

    reg.write_registry(registry)

    try:
        import files_index
        files_index.rebuild_audio()
        files_index.rebuild_visual()
    except Exception:
        pass

    print(json.dumps({
        'ok': True,
        'removed': int(removed),
        'keeper_id': keeper,
        'loser_ids': losers,
    }, ensure_ascii=False))
    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv) or 0)
