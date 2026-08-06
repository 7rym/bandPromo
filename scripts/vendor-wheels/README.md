# Offline Python wheels for bandPromo builds

These wheels are installed into `scripts/vendor/` by `build.py` when the host
Python cannot use system site-packages (typical shared hosting).

`pip install --target scripts/vendor` prefers a matching wheel for the **running**
interpreter. Supported offline ABIs:

- **cp36** (Python 3.6.9 hard floor): Pillow 8.4.0, xxhash 3.2.0 (manylinux x86_64)
- **cp38–cp312** manylinux x86_64 (plus local Windows wheels where present)

`mutagen` ships as `py3-none-any` and works across those interpreters.

Operators never run pip themselves.

Do not commit `scripts/vendor/` — that directory is created on each host.
