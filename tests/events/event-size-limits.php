<?php
/** Read-only checks: bootstrap WordPress, then require this file. */
use BCKFC\Tools\Events\TournamentSettings;

$rows = TournamentSettings::sanitizeSizeLimits([
    0 => ['species' => ' Redfish ', 'minimum' => '18″', 'maximum' => 'Must be under 26″'],
    2 => ['species' => '', 'minimum' => '', 'maximum' => ''],
    3 => ['species' => '<b>Trout</b>', 'minimum' => '13″', 'maximum' => 'Only 2 over 20″'],
    4 => ['species' => 'Flounder'],
]);

$expected = [
    ['species' => 'Redfish', 'minimum' => '18″', 'maximum' => 'Must be under 26″'],
    ['species' => 'Trout', 'minimum' => '13″', 'maximum' => 'Only 2 over 20″'],
    ['species' => 'Flounder', 'minimum' => '', 'maximum' => ''],
];

if ($rows !== $expected) {
    throw new RuntimeException('Size limits must preserve text and order, sanitize HTML, and omit empty rows.');
}

// Removing every editor row submits the hidden empty-string value.
if (TournamentSettings::sanitizeSizeLimits('') !== []) {
    throw new RuntimeException('Removing all size limits must produce an empty list.');
}

echo "Size-limit text, empty fields, removed rows, and sanitization checks passed.\n";
