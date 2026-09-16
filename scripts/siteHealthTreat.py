#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""Entry: site health Treat (apply plan)."""
from __future__ import print_function
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(HERE, 'site_health'))
from runner import main  # noqa: E402

if __name__ == '__main__':
    sys.exit(main(['--mode', 'treat']))
