<?php
require __DIR__ . '/../../includes/rodeo-export/RodeoExport.php';
require __DIR__ . '/../../includes/rodeo-export/ExportSettings.php';
use BCKFC\Tools\RodeoExport\RodeoExport;
use BCKFC\Tools\RodeoExport\ExportSettings;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function row(
    int $id,
    int $ticket,
    string $name = 'Jane Doe',
    string $email = 'jane@example.test',
    array $checked = [],
): array {
    return [
        'id' => $id,
        'ticket_id' => $ticket,
        'name' => $name,
        'email' => $email,
        'ticket' => 'Ticket ' . $ticket,
        'phone' => '',
        'emergency' => '',
        'checked' => $checked,
        'excluded' => '',
        'warnings' => [],
    ];
}

$tickets = [1 => ['checkboxes' => ['senior' => [], 'ladies' => []]], 2 => ['checkboxes' => []]];
$settings = ExportSettings::validate(
    [
        'divisions' => ['Main', 'Trout', 'Senior', 'Ladies'],
        'rules' => [
            1 => [
                'entry' => true,
                'divisions' => ['Main', 'Trout'],
                'checkboxes' => ['senior' => ['Senior'], 'ladies' => ['Ladies']],
            ],
            2 => ['entry' => false, 'divisions' => ['Trout'], 'checkboxes' => []],
        ],
    ],
    $tickets,
);
$excluded = row(5, 1);
$excluded['excluded'] = 'Cancelled';
$records = [
    row(1, 1, ' Jane   Doe ', 'JANE@example.test', ['senior', 'ladies']),
    row(2, 2),
    row(3, 2, 'Jane Doe', 'different@example.test'),
    row(4, 1, 'John Doe', 'jane@example.test'),
    $excluded,
    row(6, 99),
];
$report = RodeoExport::build($records, $settings['rules'], $settings['divisions']);
check(
    count($report['anglers']) === 3,
    'Exact normalized matches combine; different identities remain separate.',
);
$jane = array_values(
    array_filter(
        $report['anglers'],
        fn($a) => $a['first'] === 'Jane' && $a['email'] === 'jane@example.test',
    ),
)[0];
check(
    count($jane['sources']) === 2 && count($jane['divisions']) === 4,
    'Bundled ticket and checked divisions union across tickets.',
);
check(count($jane['warnings']) === 2, 'Name and shared-email warnings present.');
check(
    $report['counts'] === ['Main' => 2, 'Trout' => 3, 'Senior' => 1, 'Ladies' => 1],
    'Division counts match grouped anglers.',
);
check($report['excluded'] === ['Cancelled' => 1], 'Excluded records are explained.');
$onlyAddon = array_values(
    array_filter($report['anglers'], fn($a) => $a['email'] === 'different@example.test'),
)[0];
check(
    str_contains(implode(' ', $onlyAddon['errors']), 'No main entry'),
    'Calcutta-only entrant is flagged.',
);
check($report['errors'] === 1, 'Missing entry is flagged for review.');

$stream = fopen('php://temp', 'r+');
RodeoExport::writeCsv($stream, $report, $settings['divisions']);
rewind($stream);
$csv = stream_get_contents($stream);
fclose($stream);
check(str_starts_with($csv, "\xEF\xBB\xBF"), 'CSV includes UTF-8 BOM.');
$lines = explode("\n", trim(substr($csv, 3)));
check(count(str_getcsv($lines[0], ',', '"', '')) === 9, 'Expected Rodeo headers.');
check(count($lines) === 4, 'All anglers export, including the missing-entry row.');

foreach (array_slice($lines, 1) as $line) {
    foreach (array_slice(str_getcsv($line, ',', '"', ''), 5) as $flag) {
        check(in_array($flag, ['TRUE', 'FALSE'], true), 'Every division cell is explicit.');
    }
}

$bad = RodeoExport::build([row(7, 1, 'Single', 'bad')], $settings['rules'], $settings['divisions']);
check($bad['errors'] === 2, 'Missing last name and invalid email are flagged.');
$stream = fopen('php://temp', 'r+');
RodeoExport::writeCsv($stream, $bad, $settings['divisions']);
rewind($stream);
$badCsv = stream_get_contents($stream);
fclose($stream);
check(
    str_contains($badCsv, 'Single,,bad,'),
    'Incomplete names and invalid emails remain editable in the exported spreadsheet.',
);

foreach ([['Main', 'main'], ['Email'], ['firstName']] as $names) {
    try {
        ExportSettings::validate(['divisions' => $names, 'rules' => []], $tickets);
        throw new RuntimeException('Expected rejected headers.');
    } catch (InvalidArgumentException $e) {
    }
}

check(
    RodeoExport::build([], $settings['rules'], $settings['divisions'])['anglers'] === [],
    'Empty event supported.',
);
$passRules = $settings['rules'] + [
    3 => ['entry' => true, 'divisions' => ['Main'], 'checkboxes' => []],
];
$series = RodeoExport::build(
    [row(10, 3), row(11, 2), row(12, 2)],
    $passRules,
    $settings['divisions'],
);
check(
    count($series['anglers']) === 1 && count($series['anglers'][0]['sources']) === 3,
    'Season pass and calcutta tickets combine into one row.',
);
check(
    $series['errors'] === 0 && $series['counts']['Main'] === 1 && $series['counts']['Trout'] === 1,
    'Pass qualifies as entry and divisions are counted once.',
);

$cpr = RodeoExport::build(
    [row(20, 3), row(21, 2), row(22, 1, 'Other Angler', 'other@example.test')],
    $passRules,
    $settings['divisions'],
    3,
);
check(
    $cpr['errors'] === 1,
    'CPR requires a season pass even when another ticket is marked as entry.',
);
check(
    str_contains(implode(' ', $cpr['anglers'][0]['errors']), 'No season pass'),
    'Non-holder remains visible as an error.',
);

$stream = fopen('php://temp', 'r+');
RodeoExport::writeCsv($stream, $cpr, $settings['divisions']);
rewind($stream);
check(
    str_contains(stream_get_contents($stream), 'other@example.test'),
    'Unmatched CPR purchasers remain in CSV for spreadsheet review.',
);
fclose($stream);

$passOnly = RodeoExport::build([row(30, 3)], $passRules, $settings['divisions'], 3);
check(
    $passOnly['errors'] === 0 && count($passOnly['anglers']) === 1,
    'Pass holders without calcuttas are included.',
);

$unselected = RodeoExport::build(
    [row(31, 99, 'Invalid', 'bad')],
    $passRules,
    $settings['divisions'],
);
check(
    $unselected['anglers'] === [] && $unselected['errors'] === 0,
    'Unselected tickets are ignored entirely.',
);

$customRules = [1 => ['entry' => true, 'divisions' => ['Youth'], 'checkboxes' => []]];
$youth = RodeoExport::build([row(32, 1)], $customRules, ['Youth']);
check(
    $youth['counts'] === ['Youth' => 1] && $youth['errors'] === 0,
    'Division names and entry mappings remain configurable.',
);

foreach (['(504) 945-8949', '+1 (504) 945-8949', '504.945.8949'] as $phone) {
    check(
        RodeoExport::normalizePhone($phone) === '5049458949',
        'Formatted numbers normalize to ten digits.',
    );
}

foreach (['50494589497', '504945', '0000000000', '5040458949', 'unknown', ''] as $phone) {
    check(RodeoExport::normalizePhone($phone) === '', 'Invalid phone entries become blank.');
}

$contact = row(40, 1);
$contact['phone'] = '50494589497';
$contact['emergency'] = '(225) 555-0123';
$contactAgain = row(41, 1);
$contactAgain['phone'] = '+1 (504) 945-8949';
$contactAgain['emergency'] = '1-225-555-0123';
$contacts = RodeoExport::build([$contact, $contactAgain], $customRules, ['Youth']);
check(
    $contacts['anglers'][0]['phone'] === '5049458949',
    'A later valid number replaces an invalid blank value.',
);
check(
    $contacts['anglers'][0]['emergency'] === '2255550123',
    'Emergency contact uses the same normalization.',
);
check(
    $contacts['anglers'][0]['warnings'] === [],
    'Country code and punctuation do not cause false conflicts.',
);

$invalidContact = RodeoExport::build([$contact], $customRules, ['Youth']);
$stream = fopen('php://temp', 'r+');
RodeoExport::writeCsv($stream, $invalidContact, ['Youth']);
rewind($stream);
fgetcsv($stream, null, ',', '"', '');
$exportedContact = fgetcsv($stream, null, ',', '"', '');
fclose($stream);
check(
    $exportedContact[3] === '' && $exportedContact[4] === '2255550123',
    'CSV contains blank invalid phones and digits-only emergency numbers.',
);

echo "Export tests passed: season passes, CPR eligibility, selected tickets, custom divisions, grouping, bundles, checkboxes, warnings, exclusion, CSV flags, invalid identities, headers, empty events.\n";
