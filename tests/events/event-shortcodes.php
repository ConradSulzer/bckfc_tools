<?php
/** Read-only integration check: bootstrap local WordPress, then require this file. */
use BCKFC\Tools\Events\TournamentSettings;

if (!defined('ABSPATH') || wp_get_environment_type() !== 'local') {
    throw new RuntimeException('This check requires the local WordPress site.');
}

$events = get_posts(['post_type' => 'tribe_events', 'posts_per_page' => 2]);

if (count($events) !== 2) {
    throw new RuntimeException('This check needs two existing events.');
}

$previousPost = $GLOBALS['post'] ?? null;
$times = array_combine(array_keys(TournamentSettings::TIME_FIELDS), [
    '00:00',
    '05:00',
    '06:30',
    '14:00',
    '14:30',
    '15:00',
    '23:59',
]);
$expectedTimes = ['12:00 AM', '5:00 AM', '6:30 AM', '2:00 PM', '2:30 PM', '3:00 PM', '11:59 PM'];

// Override reads only within this process; no event records are created or changed.
$metadata = static function ($value, $id, $key) use ($events, $times) {
    if ($id !== $events[0]->ID && $id !== $events[1]->ID) {
        return $value;
    }

    if ($key === '_bckfc_times') {
        return [$id === $events[0]->ID ? $times : ['scales_close' => '16:00']];
    }

    if ($key === '_bckfc_registration_closes') {
        return [$id === $events[0]->ID ? '2027-03-19T20:00' : ''];
    }

    if ($key === '_bckfc_locations') {
        return [
            [
                'fishing' => ['location' => 'Louisiana Statewide'],
                'weigh_in' => [
                    'name' => $id === $events[0]->ID ? 'Pip\'s & Friends' : 'Other Marina',
                    'location' => 'St. Bernard Parish',
                    'address' => '123 Main Street',
                    'notes' => 'Use the east entrance.',
                ],
            ],
        ];
    }

    return $value;
};

$check = static function ($actual, $expected, $message) {
    if ($actual !== $expected) {
        throw new RuntimeException($message . ': ' . var_export($actual, true));
    }
};

add_filter('get_post_metadata', $metadata, 10, 3);

try {
    $GLOBALS['post'] = $events[0];

    $check(
        do_shortcode('[bckfc_event_date]'),
        esc_html(tribe_get_start_date($events[0]->ID, false, 'F j, Y')),
        'Native event start date',
    );
    $check(
        do_shortcode('Registration closes [bckfc_registration_closes].'),
        'Registration closes March 19, 2027 at 8:00 PM.',
        'Registration deadline in event timezone',
    );

    foreach (array_keys($times) as $index => $type) {
        $check(
            do_shortcode('[bckfc_event_time type="' . $type . '"]'),
            $expectedTimes[$index],
            $type,
        );
    }

    $check(
        do_shortcode('[bckfc_event_location type="fishing"]'),
        'Louisiana Statewide',
        'Area without name',
    );
    $check(
        do_shortcode('[bckfc_event_location type="weigh_in"]'),
        esc_html("Pip's & Friends · St. Bernard Parish"),
        'Combined location',
    );

    foreach (
        [
            'name' => "Pip's & Friends",
            'location' => 'St. Bernard Parish',
            'address' => '123 Main Street',
            'notes' => 'Use the east entrance.',
        ]
        as $field => $expected
    ) {
        $check(
            do_shortcode('[bckfc_event_location type="weigh_in" field="' . $field . '"]'),
            esc_html($expected),
            $field,
        );
    }

    $content =
        '[fusion_accordion][fusion_toggle title="Weigh-In"]Arrive by [bckfc_event_time type="scales_close"] at [bckfc_event_location type="weigh_in"].[/fusion_toggle][/fusion_accordion]';
    $rendered = do_shortcode($content);
    $check(
        str_contains(
            $rendered,
            'Arrive by 11:59 PM at ' . esc_html("Pip's & Friends · St. Bernard Parish"),
        ),
        true,
        'Avada nested shortcode processing',
    );

    $GLOBALS['post'] = $events[1];
    $check(do_shortcode('[bckfc_registration_closes]'), '', 'Unset registration deadline');
    $check(
        do_shortcode('[bckfc_event_date]'),
        esc_html(tribe_get_start_date($events[1]->ID, false, 'F j, Y')),
        'Second event start date',
    );

    $rendered = do_shortcode($content);
    $check(
        str_contains($rendered, 'Arrive by 4:00 PM at Other Marina · St. Bernard Parish.'),
        true,
        'Same content uses second event',
    );
    $check(do_shortcode('[bckfc_event_time type="launch"]'), '', 'Unset time');
    $check(do_shortcode('[bckfc_event_time type="unknown"]'), '', 'Unknown time');
    $check(do_shortcode('[bckfc_event_location type="unknown"]'), '', 'Unknown location');
    $check(
        do_shortcode('[bckfc_event_location type="weigh_in" field="unknown"]'),
        '',
        'Unknown location field',
    );

    $GLOBALS['post'] = null;
    $check(
        do_shortcode(
            '[bckfc_event_time type="scales_close"][bckfc_event_location type="weigh_in"][bckfc_event_date][bckfc_registration_closes]',
        ),
        '',
        'No current event',
    );

    echo "Event date/time/location shortcodes and Avada accordion checks passed.\n";
} finally {
    remove_filter('get_post_metadata', $metadata, 10);
    $GLOBALS['post'] = $previousPost;
}
