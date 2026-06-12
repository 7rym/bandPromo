<?php

function bandpromo_sanitize_quiz_input($data): string
{
    $data = trim((string) $data);
    $data = stripslashes($data);

    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}
