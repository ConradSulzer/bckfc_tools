<?php
namespace BCKFC\Tools\Events;

/** Tournament settings stored on existing WordPress events. */
final class TournamentSettings
{
    public const TAXONOMY = 'tribe_events_cat';
    public const SERIES_PARENT = 'championship-series';
    // Optional HH:MM values on the event day, in the event timezone.
    public const TIME_FIELDS = [
        'launch' => 'Launch',
        'shotgun_launch' => 'Shotgun Launch',
        'lines_in' => 'Lines In',
        'lines_out' => 'Lines Out',
        'submission_cutoff' => 'Submission Cutoff',
        'scales_open' => 'Scales Open',
        'scales_close' => 'Scales Close',
    ];

    public static function getChampionshipSeriesCategories(): array
    {
        $parent = get_term_by('slug', self::SERIES_PARENT, self::TAXONOMY);

        if (!$parent) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
            'parent' => $parent->term_id,
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }

    public static function getEventSettings(int $id): array
    {
        $terms = wp_get_object_terms($id, self::TAXONOMY, ['fields' => 'ids']);

        $series = is_wp_error($terms)
            ? []
            : array_values(
                array_intersect(
                    array_column(self::getChampionshipSeriesCategories(), 'term_id'),
                    $terms,
                ),
            );

        return [
            'format' => (string) get_post_meta($id, '_bckfc_format', true),
            'registration_closes' => (string) get_post_meta(
                $id,
                '_bckfc_registration_closes',
                true,
            ),
            'series' => $series,
            'championship' => $series && (bool) get_post_meta($id, '_bckfc_championship', true),
        ];
    }

    public static function saveEventSettings(int $id, array $input): void
    {
        $times = isset($input['times']) ? self::normalizeEventTimes($input['times']) : null;
        $registrationCloses = $input['registration_closes'] ?? null;

        if ($registrationCloses !== null && $registrationCloses !== '') {
            self::parseRegistrationCloseDate($id, $registrationCloses);
        }

        $format = $input['format'] ?? '';

        if (!in_array($format, ['', 'weigh_in', 'cpr_cps'], true)) {
            throw new \InvalidArgumentException('Choose Weigh-in or CPR/CPS.');
        }

        $series = filter_var($input['series'] ?? 0, FILTER_VALIDATE_INT);
        $ids = array_map(
            'intval',
            array_column(self::getChampionshipSeriesCategories(), 'term_id'),
        );

        if ($series === false || ($series !== 0 && !in_array($series, $ids, true))) {
            throw new \InvalidArgumentException(
                'Choose a yearly category under Championship Series.',
            );
        }

        $existing = wp_get_object_terms($id, self::TAXONOMY, ['fields' => 'ids']);

        if (is_wp_error($existing)) {
            throw new \RuntimeException($existing->get_error_message());
        }

        // Replace only series membership. All unrelated event categories remain intact.
        $categories = array_values(array_diff($existing, $ids));

        if ($series) {
            $categories[] = $series;
        }

        $result = wp_set_object_terms($id, array_map('intval', $categories), self::TAXONOMY);

        if (is_wp_error($result)) {
            throw new \RuntimeException($result->get_error_message());
        }

        if (isset($input['locations']) && is_array($input['locations'])) {
            update_post_meta(
                $id,
                '_bckfc_locations',
                self::sanitizeEventLocations($input['locations']),
            );
        }

        update_post_meta($id, '_bckfc_format', $format);

        if ($times !== null) {
            update_post_meta($id, '_bckfc_times', $times);
        }

        if ($registrationCloses !== null) {
            update_post_meta($id, '_bckfc_registration_closes', $registrationCloses);
        }

        update_post_meta(
            $id,
            '_bckfc_championship',
            $series && !empty($input['championship']) ? 1 : 0,
        );
    }

    public static function getEventTimes(int $eventId): array
    {
        $values = get_post_meta($eventId, '_bckfc_times', false);

        return self::normalizeEventTimes($values[0] ?? []);
    }

    public static function normalizeEventTimes($input): array
    {
        if (!is_array($input)) {
            throw new \InvalidArgumentException('Choose valid tournament times.');
        }

        $times = [];

        foreach (self::TIME_FIELDS as $key => $label) {
            $value = $input[$key] ?? '';

            if (
                !is_string($value) ||
                ($value !== '' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value))
            ) {
                throw new \InvalidArgumentException('Choose a valid ' . $label . ' time.');
            }

            $times[$key] = $value;
        }

        return $times;
    }

    public static function getRegistrationCloseDate(int $eventId): ?\DateTimeImmutable
    {
        $value = get_post_meta($eventId, '_bckfc_registration_closes', true);

        return $value === '' ? null : self::parseRegistrationCloseDate($eventId, $value);
    }

    private static function parseRegistrationCloseDate(int $eventId, $value): \DateTimeImmutable
    {
        $timezone = \Tribe__Timezones::build_timezone_object(
            \Tribe__Events__Timezones::get_event_timezone_string($eventId),
        );
        $date = is_string($value)
            ? \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $timezone)
            : false;

        if (!$date || $date->format('Y-m-d\TH:i') !== $value) {
            throw new \InvalidArgumentException(
                'Choose a valid Registration Closes date and time.',
            );
        }

        return $date;
    }

    public static function getEventLocations(int $eventId): array
    {
        $values = get_post_meta($eventId, '_bckfc_locations', false);

        return self::sanitizeEventLocations($values[0] ?? []);
    }

    public static function sanitizeEventLocations($input): array
    {
        $input = is_array($input) ? $input : [];
        $locations = [];

        foreach (['fishing', 'weigh_in'] as $group) {
            $fields = is_array($input[$group] ?? null) ? $input[$group] : [];

            foreach (['name', 'location', 'address', 'notes'] as $field) {
                $value = is_string($fields[$field] ?? null) ? $fields[$field] : '';
                $locations[$group][$field] =
                    $field === 'notes'
                        ? sanitize_textarea_field($value)
                        : sanitize_text_field($value);
            }
        }

        return $locations;
    }
}
