<?php
namespace BCKFC\Tools\Events;

/** WordPress event editor UI and request handling. No custom tables or duplicate event records. */
final class TournamentSettingsPanel
{
    public static function registerHooks(): void
    {
        add_action('add_meta_boxes_tribe_events', [self::class, 'addTournamentSettingsMetaBox']);
        add_action(
            'wp_after_insert_post',
            [self::class, 'saveTournamentSettingsFromRequest'],
            20,
            3,
        );
        add_action('set_object_terms', [self::class, 'clearChampionshipWhenSeriesRemoved'], 20, 4);
        add_action('admin_notices', [self::class, 'displayAdminErrorNotice']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueEventEditorAssets']);
    }

    public static function addTournamentSettingsMetaBox(): void
    {
        add_meta_box(
            'bckfc-tournament-settings',
            'BCKFC Tournament Settings',
            [self::class, 'renderTournamentSettings'],
            'tribe_events',
            'normal',
            'high',
        );
    }

    public static function enqueueEventEditorAssets(): void
    {
        $screen = get_current_screen();

        if ($screen?->post_type !== 'tribe_events' || $screen->base !== 'post') {
            return;
        }

        $root = dirname(__DIR__, 2);
        wp_enqueue_media();

        wp_enqueue_script(
            'bckfc-event-editor',
            plugins_url('assets/events/event-editor.js', $root . '/bckfc-tools.php'),
            ['jquery', 'autosave'],
            (string) filemtime($root . '/assets/events/event-editor.js'),
            true,
        );
        wp_enqueue_style(
            'bckfc-event-editor',
            plugins_url('assets/events/event-editor.css', $root . '/bckfc-tools.php'),
            [],
            (string) filemtime($root . '/assets/events/event-editor.css'),
        );
    }

    public static function renderTournamentSettings(\WP_Post $post): void
    {
        $settings = TournamentSettings::getEventSettings($post->ID);
        $series = TournamentSettings::getChampionshipSeriesCategories();

        wp_nonce_field('bckfc_event_' . $post->ID, 'bckfc_event_nonce');

        echo '<div class="bckfc-event-editor">';
        echo '<div class="bckfc-event-editor-tabs" role="tablist" aria-label="Tournament Settings">';
        echo '<button type="button" role="tab" id="bckfc-tournament-general-tab" aria-controls="bckfc-tournament-general" aria-selected="true" tabindex="0">General</button>';
        echo '<button type="button" role="tab" id="bckfc-tournament-locations-tab" aria-controls="bckfc-tournament-locations" aria-selected="false" tabindex="-1">Locations</button>';
        echo '</div><div class="bckfc-event-editor-panels">';
        echo '<div role="tabpanel" id="bckfc-tournament-general" aria-labelledby="bckfc-tournament-general-tab">';

        echo '<table class="eventtable eventForm"><colgroup><col style="width:15%"><col style="width:85%"></colgroup><tbody>';
        echo '<tr><td><label for="bckfc-format">Tournament Format:</label></td><td><select id="bckfc-format" name="bckfc_event[format]">';
        self::renderSelectOption('', 'Not Set', $settings['format']);
        self::renderSelectOption('weigh_in', 'Weigh-in', $settings['format']);
        self::renderSelectOption('cpr_cps', 'CPR/CPS', $settings['format']);
        echo '</select></td></tr>';

        echo '<tr><td><label for="bckfc-series">Championship Series:</label></td><td><select id="bckfc-series" name="bckfc_event[series]">';
        self::renderSelectOption('0', 'None', (string) ($settings['series'][0] ?? 0));

        foreach ($series as $term) {
            self::renderSelectOption(
                (string) $term->term_id,
                $term->name,
                (string) ($settings['series'][0] ?? 0),
            );
        }

        echo '</select>';

        if (count($settings['series']) > 1) {
            echo '<p class="description">This event has multiple series categories. Saving this panel will keep only the selected series.</p>';
        }

        if (!$series) {
            echo '<p class="description">In <a href="' .
                esc_url(
                    admin_url('edit-tags.php?taxonomy=tribe_events_cat&post_type=tribe_events'),
                ) .
                '">Event Categories</a>, create a parent named Championship Series with slug championship-series, then place yearly categories beneath it. Existing yearly categories can be moved under that parent.</p>';
        }

        echo '</td></tr><tr data-bckfc-championship' .
            (!$settings['series'] ? ' hidden' : '') .
            '><td></td><td><label><input type="checkbox" name="bckfc_event[championship]" value="1"' .
            checked($settings['championship'], true, false) .
            '> This event is the Championship</label></td></tr>';

        echo '<tr><td><label for="bckfc-registration-closes">Registration Closes:</label></td><td>';
        echo '<input type="datetime-local" id="bckfc-registration-closes" name="bckfc_event[registration_closes]" value="' .
            esc_attr($settings['registration_closes']) .
            '">';
        echo '<p class="description">Optional. Uses the event timezone. Announces the registration deadline; individual ticket sale times are set separately. Save the event before previewing a changed deadline.</p>';
        echo '</td></tr></tbody></table></div>';
        echo '<div role="tabpanel" id="bckfc-tournament-locations" aria-labelledby="bckfc-tournament-locations-tab" hidden>';
        self::renderTournamentLocations($post->ID);
        echo '</div></div></div>';
    }

    private static function renderTournamentLocations(int $eventId): void
    {
        $locations = TournamentSettings::getEventLocations($eventId);

        echo '<p class="description">Save the event before previewing location changes.</p>';

        foreach (
            ['fishing' => 'Fishing / Launch', 'weigh_in' => 'Weigh-In / Awards']
            as $group => $heading
        ) {
            echo '<h3>' . esc_html($heading) . '</h3>';
            echo '<table class="eventtable eventForm"><colgroup><col style="width:25%"><col style="width:75%"></colgroup><tbody>';

            foreach (
                [
                    'name' => 'Name',
                    'location' => 'Location',
                    'address' => 'Address or Coordinates',
                    'notes' => 'Notes',
                ]
                as $field => $label
            ) {
                $id = 'bckfc-location-' . $group . '-' . $field;
                $name = 'bckfc_event[locations][' . $group . '][' . $field . ']';
                $value = $locations[$group][$field];

                echo '<tr><td><label for="' .
                    esc_attr($id) .
                    '">' .
                    esc_html($label) .
                    ':</label></td><td>';

                if ($field === 'notes') {
                    echo '<textarea class="large-text" rows="3" id="' .
                        esc_attr($id) .
                        '" name="' .
                        esc_attr($name) .
                        '">' .
                        esc_textarea($value) .
                        '</textarea>';
                } else {
                    echo '<input type="text" class="regular-text" id="' .
                        esc_attr($id) .
                        '" name="' .
                        esc_attr($name) .
                        '" value="' .
                        esc_attr($value) .
                        '">';
                }

                if ($field === 'location') {
                    echo '<p class="description">Name and location are optional. Together they display as Name · Location.</p>';
                } elseif ($field === 'address') {
                    echo '<p class="description">Optional street address or latitude, longitude for directions.</p>';
                } elseif ($field === 'notes') {
                    echo '<p class="description">' .
                        ($group === 'fishing'
                            ? 'Launch instructions or fishing boundaries.'
                            : 'Meeting place or arrival instructions.') .
                        '</p>';
                }

                echo '</td></tr>';
            }

            echo '</tbody></table>';
        }
    }

    public static function renderSelectOption(string $value, string $label, string $selected): void
    {
        echo '<option value="' .
            esc_attr($value) .
            '"' .
            selected($selected, $value, false) .
            '>' .
            esc_html($label) .
            '</option>';
    }

    public static function saveTournamentSettingsFromRequest(
        int $id,
        \WP_Post $post,
        bool $update,
    ): void {
        if (
            $post->post_type !== 'tribe_events' ||
            wp_is_post_revision($id) ||
            wp_is_post_autosave($id) ||
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        ) {
            return;
        }

        if (
            !current_user_can('edit_post', $id) ||
            !isset($_POST['bckfc_event_nonce']) ||
            !is_string($_POST['bckfc_event_nonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['bckfc_event_nonce'])),
                'bckfc_event_' . $id,
            )
        ) {
            return;
        }

        if (!isset($_POST['bckfc_event']) || !is_array($_POST['bckfc_event'])) {
            return;
        }

        try {
            TournamentSettings::saveEventSettings($id, wp_unslash($_POST['bckfc_event']));
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            self::queueAdminErrorNotice(
                'BCKFC tournament settings were not saved: ' . $e->getMessage(),
            );
        }
    }

    public static function clearChampionshipWhenSeriesRemoved(
        int $id,
        $terms,
        array $ttIds,
        string $taxonomy,
    ): void {
        if (
            $taxonomy === TournamentSettings::TAXONOMY &&
            get_post_type($id) === 'tribe_events' &&
            !TournamentSettings::getEventSettings($id)['series']
        ) {
            delete_post_meta($id, '_bckfc_championship');
        }
    }

    public static function queueAdminErrorNotice(string $message): void
    {
        set_transient('bckfc_fields_error_' . get_current_user_id(), $message, 60);
    }

    public static function displayAdminErrorNotice(): void
    {
        $key = 'bckfc_fields_error_' . get_current_user_id();
        $message = get_transient($key);

        if (!$message) {
            return;
        }

        delete_transient($key);
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }
}
