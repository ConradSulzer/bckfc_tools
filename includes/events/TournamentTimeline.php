<?php
namespace BCKFC\Tools\Events;

/** An optional Avada element using event times and locations. */
final class TournamentTimeline
{
    public static function registerHooks(): void
    {
        add_shortcode('bckfc_tournament_timeline', [self::class, 'renderTournamentTimeline']);
        add_action('fusion_builder_before_init', [self::class, 'registerBuilderElement']);
    }

    public static function registerBuilderElement(): void
    {
        fusion_builder_map([
            'name' => 'BCKFC Times and Locations',
            'shortcode' => 'bckfc_tournament_timeline',
            'icon' => 'fusiona-clock',
            'params' => [
                [
                    'type' => 'textfield',
                    'heading' => 'Heading',
                    'param_name' => 'heading',
                    'value' => 'Tournament Times',
                ],
                [
                    'type' => 'textarea',
                    'heading' => 'Additional Details',
                    'param_name' => 'element_content',
                    'value' => '',
                    'description' =>
                        'Optional details below the times and locations. Save changes to Tournament Settings before previewing.',
                ],
            ],
        ]);
    }

    public static function renderTournamentTimeline($attributes, $content = ''): string
    {
        $eventId = get_the_ID();

        // The element reads the current event; it has no event selector for other pages.
        if (get_post_type($eventId) !== 'tribe_events') {
            return '';
        }

        $times = array_filter(
            TournamentSettings::getEventTimes($eventId),
            static fn(string $time): bool => $time !== '',
        );

        $locations = array_filter(
            TournamentSettings::getEventLocations($eventId),
            static fn(array $location): bool => $location['name'] !== '' ||
                $location['location'] !== '' ||
                $location['address'] !== '',
        );

        if (!$times && !$locations) {
            return '';
        }

        $timeLabels = TournamentSettings::getEventTimeLabels($eventId);

        asort($times, SORT_STRING);
        $attributes = shortcode_atts(['heading' => 'Tournament Times'], $attributes);
        $heading = $attributes['heading'];
        $details = wpautop(wp_kses_post($content ?? ''));

        ob_start();
        include dirname(__DIR__, 2) . '/templates/events/timeline.php';

        return ob_get_clean();
    }
}
