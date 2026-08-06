# Offline Python wheels for bandPromo builds

These wheels are installed into `scripts/vendor/` by `build.py` when the host
Python cannot use system site-packages (typical shared hosting).

`pip install --target scripts/vendor` prefers a matching wheel for the **running**
interpreter (cp38–cp312 manylinux x86_64, plus local Windows where present).
Operators never run pip themselves.

Do not commit `scripts/vendor/` — that directory is created on each host.
