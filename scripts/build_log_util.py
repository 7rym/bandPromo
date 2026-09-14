"""Shared publish-log helpers (timestamps, duration, error line detection).

Python 3.6.9+ compatible.
"""

import re
import sys
import time
from datetime import datetime, timezone

# Lines that should be repeated in the compressed end report.
_ERROR_LINE_RE = re.compile(
    r'(?i)(\bFAILED\b|finished with errors|Need attention|'
    r'Player playlist publish failed|Could not resolve PHP CLI|'
    r'Build failed at stage|❌)'
)


def utc_stamp():
    """Return HH:MM:SS in UTC."""
    return datetime.now(timezone.utc).strftime('%H:%M:%S')


def format_build_duration(seconds):
    """Human-readable elapsed time for the publish summary."""
    total = max(0, int(round(float(seconds))))
    hours, rem = divmod(total, 3600)
    minutes, secs = divmod(rem, 60)
    if hours > 0:
        return '{0}h {1}m {2}s'.format(hours, minutes, secs)
    if minutes > 0:
        return '{0}m {1}s'.format(minutes, secs)
    return '{0}s'.format(secs)


def format_stage_seconds(seconds):
    """Compact duration for stage timing tables (keeps tenths under 10s)."""
    value = max(0.0, float(seconds))
    if value < 10.0:
        return '{0:.1f}s'.format(value)
    return format_build_duration(value)


def log_line(message, error_collector=None):
    """Print a timestamped operator line and optionally collect errors."""
    text = str(message).rstrip('\n')
    stamped = '[{0}] {1}'.format(utc_stamp(), text)
    print(stamped)
    sys.stdout.flush()
    if error_collector is not None and is_error_log_line(text):
        error_collector.append(text)
    return stamped


def is_error_log_line(line):
    text = str(line).strip()
    if text == '':
        return False
    # Machine BUILD_STATS / STAGE_* markers are not operator errors.
    if text.startswith('BUILD_STATS ') or text.startswith('STAGE_'):
        return False
    return _ERROR_LINE_RE.search(text) is not None


def maybe_collect_error(line, error_collector):
    if error_collector is None:
        return
    text = str(line).rstrip('\n')
    if is_error_log_line(text):
        error_collector.append(text)


class StageTimingRecorder(object):
    """Collect per-stage wall durations for the end report."""

    def __init__(self):
        self.entries = []

    def record(self, stage_id, label, seconds, ok):
        self.entries.append({
            'id': str(stage_id or ''),
            'label': str(label or stage_id or 'stage'),
            'seconds': float(seconds),
            'ok': bool(ok),
        })

    def print_table(self):
        if not self.entries:
            return
        print('  Stage timings')
        label_width = max(len(entry['label']) for entry in self.entries)
        for entry in self.entries:
            status = 'ok' if entry['ok'] else 'FAILED'
            print('    {0}  {1}  ({2})'.format(
                entry['label'].ljust(label_width),
                format_stage_seconds(entry['seconds']).rjust(8),
                status,
            ))
        print('')
        sys.stdout.flush()


def print_repeated_errors(error_lines):
    if not error_lines:
        return
    # Deduplicate while preserving order.
    seen = set()
    unique = []
    for line in error_lines:
        key = line.strip()
        if key in seen:
            continue
        seen.add(key)
        unique.append(key)
    print('  Errors (repeated from the log)')
    for line in unique:
        print('    - ' + line)
    print('')
    sys.stdout.flush()


def monotonic_now():
    return time.monotonic()
