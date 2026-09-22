# -*- coding: utf-8 -*-
"""Treat wrappers for storage reclaim."""

from __future__ import print_function

import storage_reclaim


def treat_storage_package_prune():
    return storage_reclaim.apply_package_prune()


def treat_storage_archives_prune():
    return storage_reclaim.apply_archives_prune()
