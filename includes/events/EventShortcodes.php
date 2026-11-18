<?php
namespace BCKFC\Tools\Events;

/** Inline values from the event containing the content. */
final class EventShortcodes
{
    public static function registerHooks(): void
    {
        add_shortcode('bckfc_event_time', [self::class, 'renderEventTime']);
        add_shortcode('bckfc_event_location', [self::class, 'renderEventLocation']);
        add_shortcode('bckfc_event_date', [self::class, 'renderEventDate']);
        add_shortcode('bckfc_registration_closes', [self::class, 'renderRegistrationCloseDate']);
    }

    public static function renderEventDate(): string
    {
        $eventId = get_the_ID();

        if (get_post_type($eventId) !== 'tribe_events') {
            return '';
        }

        return esc_html(tribe_get_start_date($eventId, false, 'F j, Y'));
    }

    public static function renderRegistrationCloseDate(): string
    {
        $eventId = get_the_ID();

        if (get_post_type($eventId) !== 'tribe_events') {
            return '';
        }

        $date = TournamentSettings::getRegistrationCloseDate($eventId);

        if (!$date) {
            return '';
        }

        return esc_html(
            wp_date('F j, Y \\a\\t g:i A', $date->getTimestamp(), $date->getTimezone()),
        );
    }

    public static function renderEventTime($attributes): string
    {
        $eventId = get_the_ID();

        // Saved builder content can also be viewed outside an event.
        if (get_post_type($eventId) !== 'tribe_events') {
            return '';
        }

        $attributes = shortcode_atts(['type' => ''], $attributes);
        $times = TournamentSettings::getEventTimes($eventId);
        $time = $times[$attributes['type']] ?? '';

        if ($time === '' || $time === 'tbd') {
            return $time === 'tbd' ? 'TBD' : '';
        }

        return esc_html(\DateTimeImmutable::createFromFormat('!H:i', $time)->format('g:i A'));
    }

    public static function renderEventLocation($attributes): string
    {
        $eventId = get_the_ID();

        if (get_post_type($eventId) !== 'tribe_events') {
            return '';
        }

        $attributes = shortcode_atts(['type' => '', 'field' => 'label'], $attributes);
        $locations = TournamentSettings::getEventLocations($eventId);
        $location = $locations[$attributes['type']] ?? null;

        if (!$location) {
            return '';
        }

        if ($attributes['field'] === 'label') {
            return esc_html(
                implode(' · ', array_filter([$location['name'], $location['location']])),
            );
        }

        return esc_html($location[$attributes['field']] ?? '');
    }
}
