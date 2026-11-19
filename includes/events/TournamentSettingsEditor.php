<?php
namespace BCKFC\Tools\Events;

/** WordPress event editor UI and request handling. No custom tables or duplicate event records. */
final class TournamentSettingsEditor
{
    public static function registerHooks(): void
    {
        add_action(
            'wp_after_insert_post',
            [self::class, 'saveTournamentSettingsFromRequest'],
            20,
            3,
        );
        add_action('set_object_terms', [self::class, 'clearChampionshipWhenSeriesRemoved'], 20, 4);
        add_action('admin_notices', [self::class, 'displayAdminErrorNotice']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueEventEditorAssets']);
        add_filter('fusion_builder_allowed_post_types', [self::class, 'enableEventBuilder']);
    }

    public static function enableEventBuilder(array $postTypes): array
    {
        return array_values(array_unique([...$postTypes, 'tribe_events']));
    }

    public static function enqueueEventEditorAssets(): void
    {
        $screen = get_current_screen();

        if ($screen?->post_type !== 'tribe_events' || $screen->base !== 'post') {
            return;
        }

        $root = dirname(__DIR__, 2);

        wp_enqueue_script(
            'bckfc-event-editor',
            plugins_url('assets/events/event-editor.js', $root . '/bckfc-tools.php'),
            [],
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

    public static function renderTournamentGeneralFields(\WP_Post $post): void
    {
        $settings = TournamentSettings::getEventSettings($post->ID);
        $series = TournamentSettings::getChampionshipSeriesCategories();

        wp_nonce_field('bckfc_event_' . $post->ID, 'bckfc_event_nonce');

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

        echo '</select><input type="hidden" name="bckfc_event[original_series]" value="' .
            esc_attr((string) ($settings['series'][0] ?? 0)) .
            '">';

        if (count($settings['series']) > 1) {
            echo '<p class="description">This event has multiple series categories. Changing this selector will keep only the selected series.</p>';
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
        echo '</td></tr></tbody></table>';
        echo '<h3>Using Dates in Event Content</h3>';
        echo '<p>Paste a shortcode wherever you want a date to appear in event content. It uses the dates saved on that event, so the same saved content works across events.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Date</th><th>Shortcode</th><th>Example Output</th></tr></thead><tbody>';
        echo '<tr><td>Event Date</td><td><code>[bckfc_event_date]</code></td><td>March 20, 2027</td></tr>';
        echo '<tr><td>Registration Closes</td><td><code>[bckfc_registration_closes]</code></td><td>March 19, 2027 at 8:00 PM</td></tr>';
        echo '</tbody></table>';
        echo '<p><strong>Example:</strong> Write <code>Registration closes [bckfc_registration_closes].</code> Anglers will see: <strong>Registration closes March 19, 2027 at 8:00 PM.</strong></p>';
        echo '<p class="description">Event Date uses the event’s start date. Registration Closes uses the event timezone and displays nothing when unset. Save date changes before previewing.</p>';
    }

    public static function renderTournamentTimes(int $eventId): void
    {
        $times = TournamentSettings::getEventTimes($eventId);
        $labels = TournamentSettings::getEventTimeLabels($eventId);

        echo '<p class="description">Set a time or select TBD. Leave both empty to omit a milestone. The timeline follows this order.</p>';
        echo '<p class="description">Display labels are optional; blank uses the milestone name. Times use the event day and timezone.</p>';
        echo '<table class="eventtable eventForm bckfc-time-fields"><colgroup><col style="width:24%"><col style="width:32%"><col style="width:44%"></colgroup><thead><tr><th scope="col">Milestone</th><th scope="col">Time</th><th scope="col">Display Label (Optional)</th></tr></thead><tbody>';

        foreach (TournamentSettings::TIME_FIELDS as $key => $label) {
            $id = 'bckfc-time-' . $key;
            $isTbd = $times[$key] === 'tbd';

            echo '<tr><td><label for="' .
                esc_attr($id) .
                '">' .
                esc_html($label) .
                ':</label></td><td><div class="bckfc-time-controls">';
            echo '<input type="time" id="' .
                esc_attr($id) .
                '" name="bckfc_event[times][' .
                esc_attr($key) .
                ']" value="' .
                esc_attr($isTbd ? '' : $times[$key]) .
                '"' .
                disabled($isTbd, true, false) .
                '>';
            echo '<label><input type="checkbox" data-bckfc-time-tbd="' .
                esc_attr($id) .
                '" name="bckfc_event[times][' .
                esc_attr($key) .
                ']" value="tbd"' .
                checked($isTbd, true, false) .
                '> TBD</label>';
            echo '</div></td><td><label class="screen-reader-text" for="' .
                esc_attr($id . '-label') .
                '">' .
                esc_html($label . ' Display Label') .
                '</label>';
            echo '<input type="text" class="regular-text" id="' .
                esc_attr($id . '-label') .
                '" name="bckfc_event[time_labels][' .
                esc_attr($key) .
                ']" value="' .
                esc_attr($labels[$key]) .
                '" placeholder="' .
                esc_attr($label) .
                '">';
            echo '</td></tr>';
        }

        echo '</tbody></table>';
        echo '<h3>Using Times in Event Content</h3>';
        echo '<p>Paste a shortcode wherever you want a time to appear in event text or a rules accordion. It uses the time saved on that event, so the same saved rules work across events.</p>';
        echo '<p><strong>Example:</strong> If Scales Close is 3:00 PM, write:<br>All anglers must arrive by <code>[bckfc_event_time type="scales_close"]</code>.<br>Anglers will see: <strong>All anglers must arrive by 3:00 PM.</strong></p>';
        echo '<table class="widefat striped"><thead><tr><th>Time</th><th>Shortcode</th></tr></thead><tbody>';

        foreach (TournamentSettings::TIME_FIELDS as $key => $label) {
            echo '<tr><td>' .
                esc_html($label) .
                '</td><td><code>[bckfc_event_time type="' .
                esc_attr($key) .
                '"]</code></td></tr>';
        }

        echo '</tbody></table>';
        echo '<p class="description">Save time changes before previewing. TBD displays as TBD; a blank time leaves an empty space in the sentence.</p>';
    }

    public static function renderTournamentLocations(int $eventId): void
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

        echo '<h3>Using Locations in Event Content</h3>';
        echo '<p>Paste a shortcode wherever you want a location to appear in event text or a rules accordion. It uses the location saved on that event, so the same saved rules work across events.</p>';
        echo '<p><strong>Example:</strong> If the weigh-in Name is Delta Marina and Location is Buras, write:<br>Weigh-in is at <code>[bckfc_event_location type="weigh_in"]</code>.<br>Anglers will see: <strong>Weigh-in is at Delta Marina · Buras.</strong></p>';
        echo '<table class="widefat striped"><thead><tr><th>Location</th><th>Shortcode</th></tr></thead><tbody>';
        echo '<tr><td>Fishing / Launch</td><td><code>[bckfc_event_location type="fishing"]</code></td></tr>';
        echo '<tr><td>Weigh-In / Awards</td><td><code>[bckfc_event_location type="weigh_in"]</code></td></tr>';
        echo '</tbody></table>';
        echo '<p>These show Name · Location. To show just one field, add <code>field="name"</code>, <code>field="location"</code>, <code>field="address"</code> or <code>field="notes"</code> inside the brackets. For example, the launch address is <code>[bckfc_event_location type="fishing" field="address"]</code>.</p>';
        echo '<p class="description">Save location changes before previewing. Blank fields display nothing. Addresses appear as text, not directions links.</p>';
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
            $input = wp_unslash($_POST['bckfc_event']);

            // An untouched selector must not undo edits made in native Categories.
            if (
                isset($input['original_series']) &&
                $input['series'] === $input['original_series']
            ) {
                unset($input['series']);
            }

            TournamentSettings::saveEventSettings($id, $input);
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
