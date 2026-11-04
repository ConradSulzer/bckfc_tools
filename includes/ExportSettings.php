<?php
namespace BCKFC\Tools;

final class ExportSettings
{
    public static function validate(array $input, array $tickets): array
    {
        $divisions = $input['divisions'] ?? [];

        if (!is_array($divisions) || !$divisions) {
            throw new \InvalidArgumentException('Add at least one division.');
        }

        $reserved = array_map(
            'strtolower',
            array_merge(RodeoExport::HEADERS, [
                'Display Name',
                'External Metadata',
                'firstName',
                'lastName',
                'displayName',
                'externalMeta',
                'phone',
                'emergencyContactPhone',
            ]),
        );
        $seen = [];

        foreach ($divisions as &$division) {
            if (!is_string($division) || trim($division) === '') {
                throw new \InvalidArgumentException('Division names cannot be empty.');
            }

            $division = trim($division);
            $key = mb_strtolower($division);

            if (isset($seen[$key]) || in_array($key, $reserved, true)) {
                throw new \InvalidArgumentException(
                    'Use unique division names that differ from Rodeo contact column names.',
                );
            }

            $seen[$key] = true;
        }

        unset($division);
        $rules = [];
        $submitted = $input['rules'] ?? [];

        if (!is_array($submitted)) {
            throw new \InvalidArgumentException('Invalid ticket rules.');
        }

        $targets = static function ($values) use ($divisions): array {
            if (!is_array($values) || array_diff($values, $divisions)) {
                throw new \InvalidArgumentException('A mapping uses an unknown division.');
            }

            return array_values(array_unique($values));
        };

        foreach ($submitted as $id => $rule) {
            if (!isset($tickets[$id]) || !is_array($rule)) {
                throw new \InvalidArgumentException(
                    'A mapped ticket is no longer available. Reload the event.',
                );
            }

            $checkboxes = [];

            foreach ($rule['checkboxes'] ?? [] as $key => $values) {
                if (!isset($tickets[$id]['checkboxes'][$key])) {
                    throw new \InvalidArgumentException(
                        'A checkbox option has changed. Reload the event and update its mapping.',
                    );
                }

                $checkboxes[$key] = $targets($values);
            }

            $rules[$id] = [
                'entry' => ($rule['entry'] ?? false) === true,
                'divisions' => $targets($rule['divisions'] ?? []),
                'checkboxes' => $checkboxes,
            ];
        }

        if (
            !array_filter(
                $rules,
                static fn($r) => $r['divisions'] || array_filter($r['checkboxes']),
            )
        ) {
            throw new \InvalidArgumentException(
                'Map at least one ticket or checkbox to a division.',
            );
        }

        return [
            'divisions' => array_values($divisions),
            'rules' => $rules,
            'seasonPass' => $input['seasonPass'] ?? null,
        ];
    }
}
