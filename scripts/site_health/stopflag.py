# -*- coding: utf-8 -*-
"""Cooperative stop flag for site health jobs."""

from __future__ import print_function

import os
import time

from paths import STOP_FLAG


def clear_stop():
    try:
        if os.path.isfile(STOP_FLAG):
            os.remove(STOP_FLAG)
    except Exception:
        pass


def stop_requested():
    if not os.path.isfile(STOP_FLAG):
        return False
    try:
        age = time.time() - os.path.getmtime(STOP_FLAG)
    except Exception:
        age = 0
    if age > 7200:
        clear_stop()
        return False
    return True
