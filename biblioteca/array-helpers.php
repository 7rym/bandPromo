<?php

function bandpromo_deep_merge(array $base, array $overlay): array
{
    foreach ($overlay as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = bandpromo_deep_merge($base[$key], $value);
        } else {
            $base[$key] = $value;
        }
    }

    return $base;
}
