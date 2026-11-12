<?php
/** Run after bootstrapping local WordPress, e.g. php -r 'require ".../wp-load.php"; require "tests/event-fields.php";'. */
use BCKFC\Tools\TournamentSettings as Settings;
use BCKFC\Tools\EventEditor;
use BCKFC\Tools\TicketEditor;

if (!defined('ABSPATH') || wp_get_environment_type() !== 'local') {
    throw new RuntimeException('This integration check requires the local WordPress site.');
}

function fieldCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$posts = $terms = [];
$previousUser = get_current_user_id();
$previousPost = $_POST;
$admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
wp_set_current_user((int) $admin[0]);

try {
    $parent = get_term_by('slug', Settings::SERIES_PARENT, Settings::TAXONOMY);

    if (!$parent) {
        $created = wp_insert_term('Championship Series', Settings::TAXONOMY, [
            'slug' => Settings::SERIES_PARENT,
        ]);

        fieldCheck(!is_wp_error($created), 'Create disposable category parent.');
        $terms[] = $created['term_id'];
        $parent = get_term($created['term_id'], Settings::TAXONOMY);
    }

    foreach (['Field check series', 'Field check unrelated'] as $index => $name) {
        $term = wp_insert_term($name . ' ' . wp_generate_uuid4(), Settings::TAXONOMY, [
            'parent' => $index === 0 ? $parent->term_id : 0,
        ]);

        fieldCheck(!is_wp_error($term), 'Create disposable categories.');
        $terms[] = $term['term_id'];

        if ($index === 0) {
            $series = $term['term_id'];
        } else {
            $unrelated = $term['term_id'];
        }
    }

    $event = wp_insert_post([
        'post_type' => 'tribe_events',
        'post_status' => 'draft',
        'post_title' => 'BCKFC fields integration check',
    ]);

    $posts[] = $event;
    update_post_meta($event, '_EventStartDate', '2027-03-01 06:00:00');
    wp_set_object_terms($event, [(int) $unrelated], Settings::TAXONOMY);

    fieldCheck(
        Settings::getEventSettings($event) === ['format' => '', 'series' => [], 'championship' => false],
        'Existing events have no format, series or Championship flag.',
    );

    $input = [
        'format' => 'weigh_in',
        'series' => $series,
        'championship' => '1',
    ];

    $_POST = [
        'bckfc_event_nonce' => wp_create_nonce('bckfc_event_' . $event),
        'bckfc_event' => wp_slash($input),
    ];

    wp_update_post(['ID' => $event, 'post_title' => 'BCKFC fields saved check']);
    $saved = Settings::getEventSettings($event);

    fieldCheck(
        $saved['format'] === 'weigh_in' && $saved['championship'] && $saved['series'] === [(int) $series],
        'Real post save assigns series and Championship.',
    );

    fieldCheck(
        has_term($unrelated, Settings::TAXONOMY, $event),
        'Unrelated event category is preserved.',
    );

    $_POST['bckfc_event_nonce'] = 'invalid';
    $_POST['bckfc_event']['series'] = 0;
    wp_update_post(['ID' => $event, 'post_title' => 'BCKFC fields nonce check']);

    fieldCheck(
        Settings::getEventSettings($event)['championship'],
        'Invalid nonce does not save settings.',
    );

    $_POST['bckfc_event_nonce'] = wp_create_nonce('bckfc_event_' . $event);
    wp_set_current_user(0);
    wp_update_post(['ID' => $event, 'post_title' => 'BCKFC fields permissions check']);

    fieldCheck(
        Settings::getEventSettings($event)['championship'],
        'Missing capability does not save settings.',
    );

    wp_set_current_user((int) $admin[0]);
    $_POST = [];
    wp_update_post(['ID' => $event, 'post_title' => 'BCKFC fields no form check']);

    fieldCheck(
        Settings::getEventSettings($event)['championship'],
        'Unrelated saves preserve settings.',
    );

    try {
        Settings::saveEventSettings($event, array_replace($input, ['series' => $unrelated]));
        throw new RuntimeException('Invalid category accepted.');
    } catch (InvalidArgumentException $e) {
    }

    fieldCheck(
        Settings::getEventSettings($event)['championship'],
        'Invalid input leaves valid settings intact.',
    );

    ob_start();
    EventEditor::renderTournamentSettings(get_post($event));
    $html = ob_get_clean();

    fieldCheck(
        str_contains($html, '> This event is the Championship</label>') &&
            str_contains($html, 'Championship Series:') &&
            str_contains($html, 'bckfc-format') &&
            str_contains($html, 'CPR/CPS') &&
            !str_contains($html, 'bckfc-introduction'),
        'Event editor renders agreed labels.',
    );

    wp_set_object_terms($event, [(int) $unrelated], Settings::TAXONOMY);

    fieldCheck(
        !get_post_meta($event, '_bckfc_championship', true),
        'Native category removal clears Championship.',
    );

    Settings::saveEventSettings($event, array_replace($input, ['format' => 'cpr_cps', 'series' => 0]));

    fieldCheck(Settings::getEventSettings($event)['format'] === 'cpr_cps', 'CPR/CPS format saves.');

    fieldCheck(
        !Settings::getEventSettings($event)['championship'],
        'No series cannot be a Championship.',
    );

    fieldCheck(
        Settings::getTicketSettings(0) === ['purpose' => 'other'],
        'New tickets default to Other.',
    );

    $provider = tribe('tickets-plus.commerce.woo');
    $data = [
        'ticket_name' => 'BCKFC fields test pass',
        'ticket_price' => '10',
        'bckfc_ticket_nonce' => wp_create_nonce('bckfc_ticket_' . $event),
        'bckfc_ticket' => ['purpose' => 'season_pass'],
    ];

    $ticket = $provider->ticket_add($event, $data);

    fieldCheck((bool) $ticket, 'Installed WooCommerce ticket provider creates ticket.');

    $posts[] = $ticket;

    fieldCheck(
        Settings::getTicketSettings($ticket) === ['purpose' => 'season_pass'],
        'Ticket provider saves a season pass without selecting future events.',
    );

    Settings::saveEventSettings($event, array_replace($input, ['championship' => '0']));

    fieldCheck(Settings::seasonPassCoversEvent($ticket, $event), 'Pass covers its selling event.');

    $laterEvent = wp_insert_post([
        'post_type' => 'tribe_events',
        'post_status' => 'draft',
        'post_title' => 'Later series event coverage check',
    ]);

    $posts[] = $laterEvent;
    Settings::saveEventSettings($laterEvent, array_replace($input, ['championship' => '0']));

    fieldCheck(
        Settings::seasonPassCoversEvent($ticket, $laterEvent),
        'Pass covers an event created later in the same series.',
    );

    Settings::saveEventSettings($laterEvent, $input);

    fieldCheck(
        !Settings::seasonPassCoversEvent($ticket, $laterEvent),
        'Championship is excluded even in the same series.',
    );

    $otherSeries = wp_insert_term('Other year ' . wp_generate_uuid4(), Settings::TAXONOMY, [
        'parent' => $parent->term_id,
    ]);

    fieldCheck(!is_wp_error($otherSeries), 'Create another yearly series.');

    $terms[] = $otherSeries['term_id'];

    Settings::saveEventSettings(
        $laterEvent,
        array_replace($input, [
            'series' => $otherSeries['term_id'],
            'championship' => '0',
        ]),
    );

    fieldCheck(
        !Settings::seasonPassCoversEvent($ticket, $laterEvent),
        'Different series is excluded.',
    );

    Settings::saveEventSettings($laterEvent, array_replace($input, ['series' => 0]));

    fieldCheck(
        !Settings::seasonPassCoversEvent($ticket, $laterEvent),
        'Events without a series are excluded.',
    );

    ob_start();
    do_action('tribe_events_tickets_metabox_edit_main', $event, $ticket, 'default');
    $html = ob_get_clean();

    fieldCheck(
        str_contains($html, 'BCKFC Type:') && str_contains($html, 'Youth Entry'),
        'Ticket form extension renders.',
    );

    fieldCheck(!str_contains($html, 'Covered Events'), 'No manual coverage checklist.');

    $data['ticket_id'] = $ticket;
    $data['bckfc_ticket'] = ['purpose' => 'youth_entry'];
    $provider->ticket_add($event, $data);

    fieldCheck(
        Settings::getTicketSettings($ticket) === ['purpose' => 'youth_entry'],
        'Youth is a distinct ticket type.',
    );

    fieldCheck(
        !Settings::seasonPassCoversEvent($ticket, $event),
        'Ordinary tickets are not season passes.',
    );

    $data['bckfc_ticket_nonce'] = 'invalid';
    $data['bckfc_ticket']['purpose'] = 'entry';
    $provider->ticket_add($event, $data);

    fieldCheck(
        Settings::getTicketSettings($ticket)['purpose'] === 'youth_entry',
        'Invalid ticket nonce preserves classification.',
    );

    $data['bckfc_ticket_nonce'] = wp_create_nonce('bckfc_ticket_' . $event);
    wp_set_current_user(0);
    $provider->ticket_add($event, $data);

    fieldCheck(
        Settings::getTicketSettings($ticket)['purpose'] === 'youth_entry',
        'Ticket fields require event edit capability.',
    );

    wp_set_current_user((int) $admin[0]);
    $data['bckfc_ticket'] = ['purpose' => 'other'];
    $provider->ticket_add($event, $data);

    fieldCheck(
        Settings::getTicketSettings($ticket)['purpose'] === 'other',
        'Selecting Other saves the default type.',
    );

    echo "PASS: event saves, categories, Championship, permissions, ticket provider hooks, Youth Entry and pass coverage.\n";
} finally {
    $_POST = [];

    foreach (array_reverse($posts) as $id) {
        wp_delete_post($id, true);
    }

    foreach (array_reverse($terms) as $id) {
        wp_delete_term($id, Settings::TAXONOMY);
    }

    $_POST = $previousPost;
    wp_set_current_user($previousUser);
}
