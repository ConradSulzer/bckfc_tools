<?php
namespace BCKFC\Tools\RodeoExport;

/** Pure transformation shared by the preview and download. */
final class RodeoExport
{
    public const HEADERS = [
        'First Name',
        'Last Name',
        'Email',
        'Phone Number',
        'Emergency Contact Number',
    ];

    public static function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
    }

    public static function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value);

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        // North American numbers: ten digits, with valid area-code and exchange prefixes.
        return preg_match('/^[2-9][0-9]{2}[2-9][0-9]{6}$/', $digits) ? $digits : '';
    }

    public static function build(
        array $records,
        array $rules,
        array $divisions,
        ?int $requiredPass = null,
    ): array {
        $anglers = [];
        $excluded = [];

        foreach ($records as $record) {
            if (!isset($rules[$record['ticket_id']])) {
                continue;
            }

            $rule = $rules[$record['ticket_id']];
            $assigned = $rule['divisions'];

            foreach ($rule['checkboxes'] as $key => $targets) {
                if (in_array($key, $record['checked'], true)) {
                    $assigned = array_merge($assigned, $targets);
                }
            }

            $assigned = array_values(array_intersect($divisions, array_unique($assigned)));

            if ($record['excluded'] !== '') {
                $reason = $record['excluded'];
                $excluded[$reason] = ($excluded[$reason] ?? 0) + 1;
                continue;
            }

            $name = trim(preg_replace('/\s+/u', ' ', $record['name']));
            $parts = explode(' ', $name, 2);
            $first = $parts[0];
            $last = $parts[1] ?? '';
            $email = mb_strtolower(trim($record['email']));
            $key = json_encode([self::normalize($first), self::normalize($last), $email]);
            // Incomplete identities are never silently combined.
            if ($first === '' || $last === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $key = 'incomplete-' . $record['id'];
            }

            if (!isset($anglers[$key])) {
                $anglers[$key] = [
                    'first' => $first,
                    'last' => $last,
                    'email' => $email,
                    'phone' => '',
                    'emergency' => '',
                    'divisions' => [],
                    'entry' => false,
                    'seasonPass' => false,
                    'sources' => [],
                    'warnings' => [],
                    'errors' => [],
                ];
            }

            $angler = &$anglers[$key];
            $angler['entry'] = $angler['entry'] || $rule['entry'];
            $angler['seasonPass'] = $angler['seasonPass'] || $record['ticket_id'] === $requiredPass;
            $angler['divisions'] = array_values(
                array_unique(array_merge($angler['divisions'], $assigned)),
            );
            $angler['sources'][] = $record['id'] . ': ' . $record['ticket'];

            foreach (['phone', 'emergency'] as $field) {
                $value = self::normalizePhone($record[$field]);

                if ($value !== '' && $angler[$field] !== '' && $value !== $angler[$field]) {
                    $angler['warnings'][] =
                        'Conflicting ' . $field . ' numbers; first value retained.';
                }

                if ($angler[$field] === '') {
                    $angler[$field] = $value;
                }
            }

            foreach ($record['warnings'] as $warning) {
                $angler['warnings'][] = $warning;
            }

            unset($angler);
        }

        $names = $emails = [];

        foreach ($anglers as $key => $angler) {
            $names[self::normalize($angler['first'] . ' ' . $angler['last'])][] = $key;

            if ($angler['email'] !== '') {
                $emails[$angler['email']][] = $key;
            }
        }

        $hasEntryRules = (bool) array_filter($rules, fn(array $r): bool => $r['entry']);
        $errors = 0;

        foreach ($anglers as &$angler) {
            if ($angler['first'] === '' || $angler['last'] === '') {
                $angler['errors'][] =
                    'Missing first or last name. Review the attendee name in the spreadsheet.';
            }

            if (!filter_var($angler['email'], FILTER_VALIDATE_EMAIL)) {
                $angler['errors'][] =
                    'Missing or invalid email. Review the attendee email in the spreadsheet.';
            }

            if ($requiredPass !== null && !$angler['seasonPass']) {
                $angler['errors'][] =
                    'No season pass matched. Check the attendee name and email against the pass holder.';
            } elseif ($hasEntryRules && !$angler['entry']) {
                $angler['errors'][] =
                    'No main entry or season pass matched. Check the attendee name and email.';
            }

            if (count($names[self::normalize($angler['first'] . ' ' . $angler['last'])]) > 1) {
                $angler['warnings'][] =
                    'Same name appears with another email; review whether these are the same person.';
            }

            if ($angler['email'] !== '' && count($emails[$angler['email']]) > 1) {
                $angler['warnings'][] =
                    'Email shared with another name; may be separate family members.';
            }

            $angler['warnings'] = array_values(array_unique($angler['warnings']));
            $errors += count($angler['errors']);
        }

        unset($angler);
        usort(
            $anglers,
            fn(array $a, array $b): int => strcasecmp(
                $a['last'] . ' ' . $a['first'],
                $b['last'] . ' ' . $b['first'],
            ),
        );
        $counts = array_fill_keys($divisions, 0);

        foreach ($anglers as $angler) {
            foreach ($angler['divisions'] as $division) {
                $counts[$division]++;
            }
        }

        return [
            'anglers' => $anglers,
            'excluded' => $excluded,
            'counts' => $counts,
            'errors' => $errors,
        ];
    }

    public static function writeCsv($stream, array $report, array $divisions): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, array_merge(self::HEADERS, $divisions), ',', '"', '');

        foreach ($report['anglers'] as $angler) {
            $row = [
                $angler['first'],
                $angler['last'],
                $angler['email'],
                $angler['phone'],
                $angler['emergency'],
            ];

            foreach ($divisions as $division) {
                $row[] = in_array($division, $angler['divisions'], true) ? 'TRUE' : 'FALSE';
            }

            fputcsv($stream, $row, ',', '"', '');
        }
    }
}
