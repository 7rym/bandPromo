"""Idempotent UTF-8 stdio setup for bandPromo Python scripts.

Multiple scripts historically wrapped sys.stdout with TextIOWrapper on import.
Importing more than one of those modules could close the underlying buffer when
the first wrapper was garbage-collected, causing:
  ValueError: I/O operation on closed file
when a later print() tried to emit JSON to PHP.

Keep this module compatible with older host Pythons (HITZ may be < 3.7).
"""

import io
import sys


def configure():
    if getattr(sys, '_bandpromo_utf8_stdio', False):
        return

    for stream_name in ('stdout', 'stderr'):
        stream = getattr(sys, stream_name, None)
        if stream is None:
            continue
        try:
            if hasattr(stream, 'reconfigure'):
                stream.reconfigure(encoding='utf-8', errors='replace', line_buffering=True)
            elif hasattr(stream, 'detach'):
                raw = stream.detach()
                setattr(
                    sys,
                    stream_name,
                    io.TextIOWrapper(raw, encoding='utf-8', errors='replace', line_buffering=True),
                )
            elif hasattr(stream, 'buffer'):
                setattr(
                    sys,
                    stream_name,
                    io.TextIOWrapper(stream.buffer, encoding='utf-8', errors='replace', line_buffering=True),
                )
        except Exception:
            # Keep the original stream if reconfiguration is unavailable.
            pass

    sys._bandpromo_utf8_stdio = True
