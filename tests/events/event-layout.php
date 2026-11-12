<?php
/** Run with the local WordPress bootstrap, like tests/events/event-fields.php. */
use BCKFC\Tools\Events\BckfcEventLayout;

if (!defined('ABSPATH') || wp_get_environment_type() !== 'local') {
    throw new RuntimeException('This check requires the local WordPress site.');
}

function layoutCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$previousPost = $_POST;
$previousUser = get_current_user_id();
$admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
wp_set_current_user((int) $admin[0]);
$eventId = wp_insert_post([
    'post_type' => 'tribe_events',
    'post_status' => 'draft',
    'post_title' => 'Layout integration check',
]);

update_post_meta($eventId, '_EventStartDate', '2099-03-20 06:00:00');
update_post_meta($eventId, '_EventEndDate', '2099-03-20 17:00:00');
update_post_meta($eventId, '_EventTimezone', 'America/Chicago');
update_post_meta($eventId, '_EventStartDateUTC', '2099-03-20 11:00:00');
update_post_meta($eventId, '_EventEndDateUTC', '2099-03-20 22:00:00');

try {
    layoutCheck(
        !BckfcEventLayout::usesCustomTemplate($eventId),
        'Existing events retain their default template.',
    );
    layoutCheck(
        in_array(BckfcEventLayout::LAYOUT_SETTINGS_KEY, wp_post_revision_meta_keys('tribe_events'), true),
        'Layout metadata supports revisions.',
    );
    layoutCheck(
        in_array('tribe_events', FusionBuilder::allowed_post_types(), true),
        'Builder is available for events.',
    );

    $_POST = [
        'bckfc_layout_nonce' => wp_create_nonce('bckfc_layout_' . $eventId),
        BckfcEventLayout::LAYOUT_SETTINGS_KEY => [
            'template' => 'bckfc',
            'alert' => 'Weather <script>bad()</script>',
            'primary_url' => 'https://example.com/register',
            'secondary_url' => '#rules',
            'image' => $eventId,
        ],
    ];
    wp_update_post(['ID' => $eventId, 'post_title' => 'Layout save check']);
    $saved = BckfcEventLayout::getLayoutSettings($eventId);

    layoutCheck(
        $saved['template'] === 'bckfc' && $saved['image'] === 0,
        'Save layout and reject non-image attachments.',
    );
    layoutCheck(
        !str_contains($saved['alert'], '<script>') && $saved['secondary_url'] === '#rules',
        'Sanitize text and preserve section links.',
    );

    $_POST['bckfc_layout_nonce'] = 'invalid';
    $_POST[BckfcEventLayout::LAYOUT_SETTINGS_KEY]['template'] = '';
    wp_update_post(['ID' => $eventId]);
    layoutCheck(BckfcEventLayout::usesCustomTemplate($eventId), 'Invalid nonce cannot change layout.');

    ob_start();
    BckfcEventLayout::renderEventHeader($eventId, false);
    $header = ob_get_clean();
    layoutCheck(
        substr_count($header, '<h1>') === 1 && (bool) preg_match('/>\s*Register\s*</', $header),
        'One event title and Register default.',
    );
    layoutCheck(
        str_contains($header, 'https://example.com/register') &&
            !str_contains($header, 'bckfc-button--outline'),
        'External registration works and incomplete secondary button is omitted.',
    );

    $pastEventId = wp_insert_post([
        'post_type' => 'tribe_events',
        'post_title' => 'Past event layout check',
        'post_status' => 'draft',
        'meta_input' => [
            '_EventStartDate' => '2020-03-20 06:00:00',
            '_EventEndDate' => '2020-03-20 17:00:00',
            '_EventStartDateUTC' => '2020-03-20 11:00:00',
            '_EventEndDateUTC' => '2020-03-20 22:00:00',
            '_EventTimezone' => 'America/Chicago',
        ],
    ]);

    try {
        update_post_meta(
            $pastEventId,
            BckfcEventLayout::LAYOUT_SETTINGS_KEY,
            array_merge($saved, [
                'secondary_label' => 'Rules',
                'secondary_url' => '#rules',
            ]),
        );

        ob_start();
        BckfcEventLayout::renderEventHeader($pastEventId, false);
        $pastHeader = ob_get_clean();

        layoutCheck(
            !str_contains($pastHeader, 'class="bckfc-button'),
            'Past events hide both header buttons, including explicit external links.',
        );
    } finally {
        wp_delete_post($pastEventId, true);
    }

    foreach (
        [
            [
                'name' => "Pip's",
                'location' => 'St. Bernard Parish',
                'expected' => "Pip's · St. Bernard Parish",
            ],
            [
                'name' => '',
                'location' => 'Louisiana Statewide',
                'expected' => 'Louisiana Statewide',
            ],
            ['name' => "Pip's", 'location' => '', 'expected' => "Pip's"],
            ['name' => '', 'location' => '', 'expected' => ''],
        ]
        as $example
    ) {
        update_post_meta($eventId, '_bckfc_locations', [
            'fishing' => $example,
            'weigh_in' => ['name' => 'Awards Only'],
        ]);

        ob_start();
        BckfcEventLayout::renderEventHeader($eventId, false);
        $locationHeader = ob_get_clean();
        layoutCheck(
            !str_contains($locationHeader, 'Awards Only'),
            'Header uses fishing location, not awards location.',
        );
        layoutCheck(
            substr_count($locationHeader, 'class="bckfc-event-fact"') ===
                ($example['expected'] === '' ? 1 : 2),
            'Empty fishing location omits the location row.',
        );

        if ($example['expected'] !== '') {
            layoutCheck(
                str_contains(
                    $locationHeader,
                    '<span>' . esc_html($example['expected']) . '</span>',
                ),
                'Header joins only the populated fishing location fields.',
            );
        }
    }

    require_once ABSPATH . 'wp-admin/includes/post.php';
    $_POST = [BckfcEventLayout::LAYOUT_SETTINGS_KEY => array_merge($saved, ['alert' => 'Preview only'])];
    $revisionId = wp_create_post_autosave([
        'post_ID' => $eventId,
        'post_type' => 'tribe_events',
        'post_title' => 'Preview title',
        'post_content' => 'Preview content',
    ]);
    layoutCheck(!is_wp_error($revisionId) && $revisionId > 0, 'Create preview revision.');
    layoutCheck(
        get_post_meta($revisionId, BckfcEventLayout::LAYOUT_SETTINGS_KEY, true)['alert'] === 'Preview only',
        'Preview captures unsaved layout fields.',
    );
    layoutCheck(
        BckfcEventLayout::getLayoutSettings($eventId)['alert'] === $saved['alert'],
        'Preview leaves saved layout unchanged.',
    );

    $previousGlobalPost = $GLOBALS['post'] ?? null;
    $GLOBALS['post'] = get_post($eventId);
    add_filter('get_post_metadata', '_wp_preview_meta_filter', 10, 4);
    set_error_handler(static function ($severity, $message, $file, $line): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    try {
        layoutCheck(
            BckfcEventLayout::getLayoutSettings($eventId)['alert'] === 'Preview only',
            'The preview filter returns the complete unsaved layout without warnings.',
        );
        layoutCheck(
            BckfcEventLayout::usesCustomTemplate($eventId),
            'Preview retains the BCKFC template selection.',
        );

        ob_start();
        BckfcEventLayout::renderEventHeader($eventId, false);
        $previewHeader = ob_get_clean();
        layoutCheck(str_contains($previewHeader, 'Preview only'), 'Header renders preview values.');
    } finally {
        restore_error_handler();
        remove_filter('get_post_metadata', '_wp_preview_meta_filter', 10);
        $GLOBALS['post'] = $previousGlobalPost;
    }

    layoutCheck(
        BckfcEventLayout::getLayoutSettings($eventId)['alert'] === $saved['alert'],
        'Reading the preview does not overwrite saved settings.',
    );

    update_post_meta($eventId, BckfcEventLayout::LAYOUT_SETTINGS_KEY, ['template' => 'bckfc']);
    ob_start();
    BckfcEventLayout::renderEventHeader($eventId, false);
    $header = ob_get_clean();
    layoutCheck(
        !str_contains($header, 'class="bckfc-event-alert"') &&
            !str_contains($header, 'href="#bckfc-tickets"'),
        'Empty alert and ticketless registration CTA are omitted.',
    );

    update_post_meta($eventId, BckfcEventLayout::LAYOUT_SETTINGS_KEY, $saved);

    $_POST = [];
    $previousGlobalPost = $GLOBALS['post'] ?? null;
    $GLOBALS['post'] = get_post($eventId);

    try {
        ob_start();
        BckfcEventLayout::renderRegistrationDeadline();
        layoutCheck(ob_get_clean() === '', 'Unset registration deadline has no callout.');

        update_post_meta($eventId, '_EventTimezone', 'America/Chicago');
        update_post_meta($eventId, '_bckfc_registration_closes', '2027-03-19T20:00');
        ob_start();
        BckfcEventLayout::renderRegistrationDeadline();
        $deadlineMarkup = ob_get_clean();

        layoutCheck(
            str_contains($deadlineMarkup, 'Registration closes') &&
                str_contains($deadlineMarkup, 'Fri, Mar 19, 2027 at 8:00 PM CDT') &&
                str_contains($deadlineMarkup, '2027-03-19T20:00:00-05:00') &&
                str_contains($deadlineMarkup, '<svg'),
            'Registration callout includes clock, local deadline and machine-readable offset.',
        );

        $previousQuery = $GLOBALS['wp_query'];
        $renderNativeTicketTitle = static function (): void {
            $template = new Tribe__Tickets__Editor__Template();
            $template->template('v2/tickets/title');
        };
        $provider = tribe('tickets-plus.commerce.woo');
        update_post_meta(
            $eventId,
            tribe('tickets.handler')->key_provider_field,
            get_class($provider),
        );
        $ticketIds = [];
        $nativeHook = $provider->get_ticket_form_hook();
        $originalCallbacks = isset($GLOBALS['wp_filter'][$nativeHook])
            ? clone $GLOBALS['wp_filter'][$nativeHook]
            : null;
        $provider->hook();

        try {
            $query = new WP_Query();
            $query->query_vars = [
                'fields' => '',
                'update_post_term_cache' => false,
                'update_post_meta_cache' => false,
            ];
            $query->is_single = true;
            $query->is_singular = true;
            $query->queried_object = get_post($eventId);
            $query->queried_object_id = $eventId;
            $query->posts = [get_post($eventId)];
            $query->post_count = 1;
            $GLOBALS['wp_query'] = $query;

            update_post_meta($eventId, '_EventStartDate', '2099-03-20 06:00:00');
            tribe_update_event($eventId, [
                'EventStartDate' => '2099-03-20 06:00:00',
                'EventEndDate' => '2099-03-20 17:00:00',
            ]);

            update_post_meta($eventId, BckfcEventLayout::LAYOUT_SETTINGS_KEY, ['template' => 'bckfc']);

            // No-ticket events still render their content, without an empty ticket section.
            ob_start();
            include dirname(__DIR__, 2) . '/templates/events/single-event.php';
            $noTicketsMarkup = ob_get_clean();
            layoutCheck(
                !str_contains($noTicketsMarkup, 'id="bckfc-tickets"') &&
                    !str_contains($noTicketsMarkup, 'href="#bckfc-tickets"'),
                'No-ticket events omit the empty ticket section.',
            );
            $query->rewind_posts();

            $renderOtherIntegration = static function (): void {
                echo '<p>Other event integration</p>';
            };
            add_action($nativeHook, $renderOtherIntegration);

            ob_start();
            include dirname(__DIR__, 2) . '/templates/events/single-event.php';
            $otherIntegrationMarkup = ob_get_clean();
            remove_action($nativeHook, $renderOtherIntegration);
            $query->rewind_posts();

            layoutCheck(
                str_contains($otherIntegrationMarkup, 'Other event integration') &&
                    !str_contains($otherIntegrationMarkup, 'href="#bckfc-tickets"'),
                'Unrelated hook output is retained without enabling the registration link.',
            );

            foreach (['Entry', 'Calcutta'] as $name) {
                $ticketId = $provider->ticket_add($eventId, [
                    'ticket_name' => 'Layout check ' . $name,
                    'ticket_price' => '10',
                    'tribe-ticket' => ['mode' => 'own', 'capacity' => '100', 'stock' => '100'],
                    'ticket_start_date' => '2020-01-01',
                    'ticket_end_date' => '2099-03-19',
                ]);
                layoutCheck((bool) $ticketId, 'Create a native WooCommerce ticket fixture.');
                $ticketIds[] = $ticketId;
            }

            update_post_meta($eventId, BckfcEventLayout::LAYOUT_SETTINGS_KEY, ['template' => 'bckfc']);
            ob_start();
            include dirname(__DIR__, 2) . '/templates/events/single-event.php';
            $eventMarkup = ob_get_clean();

            layoutCheck(
                str_contains($eventMarkup, 'tribe-tickets__tickets-title') &&
                    substr_count($eventMarkup, 'class="bckfc-registration-deadline"') === 1,
                'The event template inserts the deadline through the native ticket title hook.',
            );

            layoutCheck(
                str_contains($eventMarkup, 'href="#bckfc-tickets"'),
                'A rendered native picker enables the default registration link.',
            );
            layoutCheck(
                strpos($eventMarkup, 'bckfc-event-hero') < strpos($eventMarkup, 'bckfc-event-body') &&
                    strpos($eventMarkup, 'bckfc-event-body') < strpos($eventMarkup, 'id="bckfc-tickets"'),
                'Preparing content first preserves the displayed header, content, tickets order.',
            );

            $sectionStart = strpos($eventMarkup, '<section id="bckfc-tickets"');
            $sectionEnd = strpos($eventMarkup, '</section>', $sectionStart ?: 0);
            layoutCheck($sectionStart !== false && $sectionEnd !== false, 'Render ticket section.');
            layoutCheck(
                substr_count($eventMarkup, 'id="tribe-tickets__tickets-form"') === 1,
                'The native ticket form renders exactly once.',
            );

            foreach ($ticketIds as $ticketId) {
                $rowId = 'id="tribe-block-tickets-item-' . $ticketId . '"';
                $rowStart = strpos($eventMarkup, $rowId);
                layoutCheck(
                    substr_count($eventMarkup, $rowId) === 1 &&
                        $rowStart > $sectionStart &&
                        $rowStart < $sectionEnd,
                    'Each real ticket row appears once inside the ticket section.',
                );
            }

            foreach ($ticketIds as $ticketId) {
                $product = wc_get_product($ticketId);
                $product->set_stock_quantity(0);
                $product->set_stock_status('outofstock');
                $product->save();
                wp_cache_delete($ticketId, 'tec_tickets');
            }
            $provider->clear_ticket_cache_for_post($eventId);
            $query->rewind_posts();

            ob_start();
            include dirname(__DIR__, 2) . '/templates/events/single-event.php';
            $soldOutMarkup = ob_get_clean();

            layoutCheck(
                str_contains($soldOutMarkup, 'id="tribe-tickets__tickets-form"') &&
                    str_contains($soldOutMarkup, 'href="#bckfc-tickets"'),
                'A sold-out native picker still receives the registration link.',
            );

            layoutCheck(
                !str_contains($eventMarkup, 'tribe-events-cal-links') &&
                    !str_contains($eventMarkup, 'tribe-events-c-subscribe-dropdown'),
                'BCKFC events omit native calendar controls.',
            );
            layoutCheck(
                BckfcEventLayout::filterEventCalendarLinks('calendar links') === '',
                'Calendar links are suppressed for the custom event template.',
            );
            update_post_meta($eventId, BckfcEventLayout::LAYOUT_SETTINGS_KEY, ['template' => '']);
            layoutCheck(
                BckfcEventLayout::filterEventCalendarLinks('calendar links') === 'calendar links',
                'Default events retain calendar links.',
            );
            update_post_meta($eventId, BckfcEventLayout::LAYOUT_SETTINGS_KEY, $saved);

            ob_start();
            $renderNativeTicketTitle();
            $standaloneTitle = ob_get_clean();

            layoutCheck(
                !str_contains($standaloneTitle, 'bckfc-registration-deadline'),
                'The deadline hook is removed after rendering the event tickets.',
            );
        } finally {
            foreach ($ticketIds as $ticketId) {
                wp_delete_post($ticketId, true);
            }

            if ($originalCallbacks) {
                $GLOBALS['wp_filter'][$nativeHook] = $originalCallbacks;
            } else {
                unset($GLOBALS['wp_filter'][$nativeHook]);
            }

            $GLOBALS['wp_query'] = $previousQuery;
        }
    } finally {
        $GLOBALS['post'] = $previousGlobalPost;
    }

    echo "PASS: layout save permissions, sanitization, builder availability, optional header elements and preview isolation.\n";
} finally {
    $_POST = $previousPost;
    wp_delete_post($eventId, true);
    wp_set_current_user($previousUser);
}
