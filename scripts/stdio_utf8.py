"""Idempotent UTF-8 stdio + process env helpers for bandPromo Python scripts.

Multiple scripts historically wrapped sys.stdout with TextIOWrapper on import.
Importing more than one of those modules could close the underlying buffer when
the first wrapper was garbage-collected, causing:
  ValueError: I/O operation on closed file
when a later print() tried to emit JSON to PHP.

Keep this module compatible with older host Pythons (HITZ may be < 3.7).

Policy: always aim for UTF-8. If the host locale is ASCII-only, reconfigure
what we can and use errors='replace' / safe print so publish never dies mid-run.
"""

from __future__ import print_function

import io
import os
import sys


def _stream_encoding(stream):
    if stream is None:
        return ''
    encoding = getattr(stream, 'encoding', None)
    if encoding:
        return str(encoding).strip().lower()
    return ''


def is_utf8_encoding(encoding):
    name = str(encoding or '').strip().lower().replace('-', '')
    return name in ('utf8', 'utf8sig', 'cp65001')


def ensure_utf8_process_env(environ=None):
    """Mutate env (default os.environ) toward UTF-8 subprocess behaviour."""
    env = os.environ if environ is None else environ
    env['PYTHONIOENCODING'] = 'utf-8:replace'
    # Do not clobber an already UTF-8 locale; fill empties / ASCII-ish defaults.
    for key in ('LANG', 'LC_ALL', 'LC_CTYPE'):
        current = str(env.get(key) or '').strip()
        if current == '' or current.upper() in ('C', 'POSIX', 'C.ASCII'):
            env[key] = 'C.UTF-8'
    return env


def configure():
    """Force UTF-8 stdio with replacement errors when possible.

    Never double-wrap sys.stdout.buffer — that closes the previous TextIOWrapper
    on GC and causes ValueError: I/O operation on closed file (HITZ / Py 3.6).
    """
    ensure_utf8_process_env()

    if getattr(sys, '_bandpromo_utf8_stdio', False):
        return report()

    for stream_name in ('stdout', 'stderr'):
        stream = getattr(sys, stream_name, None)
        if stream is None:
            continue
        current = _stream_encoding(stream)
        if is_utf8_encoding(current):
            continue
        try:
            if hasattr(stream, 'reconfigure'):
                stream.reconfigure(encoding='utf-8', errors='replace', line_buffering=True)
                continue
            # Python 3.6 path: wrap the underlying buffer once only.
            buffer_obj = getattr(stream, 'buffer', None)
            if buffer_obj is not None:
                setattr(
                    sys,
                    stream_name,
                    io.TextIOWrapper(
                        buffer_obj,
                        encoding='utf-8',
                        errors='replace',
                        line_buffering=True,
                    ),
                )
                # Drop the old wrapper reference so we do not keep two owners;
                # do not call detach() — that closes the buffer for other wrappers.
        except Exception:
            pass

    sys._bandpromo_utf8_stdio = True
    return report()


def repair():
    """
    Recover after ValueError: I/O operation on closed file from a double-wrap.

    Restores sys.stdout/stderr from __stdout__/__stderr__ when the live
    wrappers are dead, then re-runs configure().
    """
    sys._bandpromo_utf8_stdio = False
    for stream_name in ('stdout', 'stderr'):
        stream = getattr(sys, stream_name, None)
        broken = False
        if stream is None:
            broken = True
        else:
            try:
                stream.write('')
                stream.flush()
            except Exception:
                broken = True
        if broken:
            original = getattr(sys, '__{0}__'.format(stream_name), None)
            if original is not None:
                setattr(sys, stream_name, original)
    return configure()


def safe_print(*args, **kwargs):
    """print() that repairs closed stdio once, then swallows further I/O errors."""
    try:
        print(*args, **kwargs)
        return
    except Exception:
        pass
    try:
        repair()
        print(*args, **kwargs)
    except Exception:
        pass


def report():
    """
    Snapshot of process text encoding health.

    Returns dict with keys: ok (bool), stdout, stderr, filesystem,
    pythonioencoding, lang, notes (list of str).
    """
    stdout_enc = _stream_encoding(getattr(sys, 'stdout', None))
    stderr_enc = _stream_encoding(getattr(sys, 'stderr', None))
    fs_enc = ''
    try:
        fs_enc = str(sys.getfilesystemencoding() or '').strip().lower()
    except Exception:
        fs_enc = ''

    notes = []
    ok = True
    if not is_utf8_encoding(stdout_enc):
        ok = False
        notes.append('stdout encoding is {0} (want utf-8)'.format(stdout_enc or 'unknown'))
    if not is_utf8_encoding(stderr_enc):
        ok = False
        notes.append('stderr encoding is {0} (want utf-8)'.format(stderr_enc or 'unknown'))
    if fs_enc and not is_utf8_encoding(fs_enc) and fs_enc not in ('mbcs',):
        # Windows mbcs for filesystem names is common; note but do not fail alone.
        notes.append('filesystem encoding is {0}'.format(fs_enc))

    return {
        'ok': ok,
        'stdout': stdout_enc or 'unknown',
        'stderr': stderr_enc or 'unknown',
        'filesystem': fs_enc or 'unknown',
        'pythonioencoding': str(os.environ.get('PYTHONIOENCODING') or ''),
        'lang': str(os.environ.get('LANG') or os.environ.get('LC_ALL') or ''),
        'notes': notes,
    }


def log_preflight(printer=None):
    """
    Configure UTF-8, then print a short preflight line.
    Returns the report dict. Does not abort the build — conversion fallbacks remain.
    """
    emit = printer if callable(printer) else print
    before = report()
    after = configure()
    if after.get('ok'):
        emit('[encoding] UTF-8 stdio ready (stdout={0}).'.format(after.get('stdout')))
    else:
        emit('[encoding] Warning: host text encoding is not UTF-8.')
        for note in after.get('notes') or []:
            emit('[encoding]   - ' + str(note))
        emit('[encoding] Reconfigured where possible; using UTF-8 with replace for pipes/files.')
        if before.get('stdout') != after.get('stdout'):
            emit(
                '[encoding] stdout {0} -> {1}'.format(
                    before.get('stdout'),
                    after.get('stdout'),
                )
            )
    return after


def open_text_read(path):
    """Read text accepting CRLF or LF; decode as UTF-8 with replace."""
    return io.open(path, 'r', encoding='utf-8', errors='replace', newline=None)


def open_text_write(path):
    """Write text as UTF-8 with LF newlines (cross-platform canonical form)."""
    return io.open(path, 'w', encoding='utf-8', errors='strict', newline='\n')


def normalize_newlines(text):
    """Normalize any CR/LF mix to LF."""
    if text is None:
        return ''
    return str(text).replace('\r\n', '\n').replace('\r', '\n')
