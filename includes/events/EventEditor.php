<?php
namespace BCKFC\Tools\Events;

/** WordPress event editor UI and request handling. No custom tables or duplicate event records. */
final class EventEditor
{
  public static function registerHooks(): void
  {
    add_action('add_meta_boxes_tribe_events', [self::class, 'addTournamentSettingsMetaBox']);
    add_action('wp_after_insert_post', [self::class, 'saveTournamentSettingsFromRequest'], 20, 3);
    add_action('set_object_terms', [self::class, 'clearChampionshipWhenSeriesRemoved'], 20, 4);
    add_action('admin_notices', [self::class, 'displayAdminErrorNotice']);
    add_action('admin_enqueue_scripts', [self::class, 'enqueueEditorScripts']);
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

  public static function enqueueEditorScripts(): void
  {
    if (get_current_screen()?->post_type !== 'tribe_events') {
      return;
    }

    wp_enqueue_script(
      'bckfc-event-fields',
      plugins_url('assets/events/event-fields.js', dirname(__DIR__, 2) . '/bckfc-tools.php'),
      [],
      (string) filemtime(dirname(__DIR__, 2) . '/assets/events/event-fields.js'),
      true,
    );
  }

  public static function renderTournamentSettings(\WP_Post $post): void
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

    echo '</select>';

    if (count($settings['series']) > 1) {
      echo '<p class="description">This event has multiple series categories. Saving this panel will keep only the selected series.</p>';
    }

    if (!$series) {
      echo '<p class="description">In <a href="' .
        esc_url(admin_url('edit-tags.php?taxonomy=tribe_events_cat&post_type=tribe_events')) .
        '">Event Categories</a>, create a parent named Championship Series with slug championship-series, then place yearly categories beneath it. Existing yearly categories can be moved under that parent.</p>';
    }

    echo '</td></tr><tr data-bckfc-championship' .
      (!$settings['series'] ? ' hidden' : '') .
      '><td></td><td><label><input type="checkbox" name="bckfc_event[championship]" value="1"' .
      checked($settings['championship'], true, false) .
      '> This event is the Championship</label></td></tr>';

    echo '</tbody></table>';
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

  public static function saveTournamentSettingsFromRequest(int $id, \WP_Post $post, bool $update): void
  {
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
      self::queueAdminErrorNotice('BCKFC tournament settings were not saved: ' . $e->getMessage());
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
