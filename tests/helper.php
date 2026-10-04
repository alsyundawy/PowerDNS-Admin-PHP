<?php

declare(strict_types=1);

/**
 * Common test assertion helper.
 */
function assertEq(mixed $actual, mixed $expected, string $desc): void
{
    if ($actual !== $expected) {
        fwrite(
            STDERR,
            "FAIL: $desc\nExpected: " . var_export($expected, true) .
            "\nActual:   " . var_export($actual, true) . "\n"
        );
        exit(1);
    }
    echo "PASS: $desc\n";
}
