<?php

declare(strict_types=1);

function csrfToken(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfRotate(): string
{
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

function csrfCheck(): void
{
    $sent = (string) ($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $known = (string) ($_SESSION['csrf'] ?? '');
    if ($sent === '' || $known === '' || !hash_equals($known, $sent)) {
        http_response_code(419);
        exit('Invalid CSRF token. Please reload the page and try again.');
    }
}
