<?php
namespace BCKFC\Tools\Events;

/** Shared meanings and metadata on existing WordPress events and WooCommerce tickets. */
final class TournamentSettings
{
    public const TAXONOMY = 'tribe_events_cat';
    public const SERIES_PARENT = 'championship-series';
    public const PURPOSES = [
        'entry' => 'Entry',
        'youth_entry' => 'Youth Entry',
        'calcutta' => 'Calcutta',
        'raffle' => 'Raffle',
        'season_pass' => 'Season Pass',
        'food_fun' => 'Food & Fun',
        'merchandise' => 'Merchandise',
        'other' => 'Other',
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
            'series' => $series,
            'championship' => $series && (bool) get_post_meta($id, '_bckfc_championship', true),
        ];
    }

    public static function saveEventSettings(int $id, array $input): void
    {
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

        update_post_meta($id, '_bckfc_format', $format);

        update_post_meta(
            $id,
            '_bckfc_championship',
            $series && !empty($input['championship']) ? 1 : 0,
        );
    }

    public static function getTicketSettings(int $id): array
    {
        return [
            'purpose' => (string) get_post_meta($id, '_bckfc_ticket_purpose', true) ?: 'other',
        ];
    }

    public static function saveTicketSettings(int $id, array $input): void
    {
        $purpose = $input['purpose'] ?? 'other';

        if (!is_string($purpose) || !isset(self::PURPOSES[$purpose])) {
            throw new \InvalidArgumentException('Choose a valid BCKFC Type.');
        }

        update_post_meta($id, '_bckfc_ticket_purpose', $purpose);
    }

    public static function seasonPassCoversEvent(int $ticketId, int $eventId): bool
    {
        if (self::getTicketSettings($ticketId)['purpose'] !== 'season_pass') {
            return false;
        }

        $sellingEventId = (int) get_post_meta($ticketId, '_tribe_wooticket_for_event', true);
        $sellingEvent = self::getEventSettings($sellingEventId);
        $event = self::getEventSettings($eventId);

        return !$event['championship'] &&
            (bool) array_intersect($sellingEvent['series'], $event['series']);
    }
}
