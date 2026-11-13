<?php
namespace BCKFC\Tools\Events;

final class BckfcEventLayout
{
  /*
   * Layout settings are stored as one associative array in the event's post
   * metadata under this key. The editor also uses it to group submitted fields.
   *
   * Field             Type / default    Purpose
   * template          string / ''       'bckfc' selects this layout; '' uses the default.
   * image             int / 0           Featured image attachment ID; 0 means none.
   * background        int / 0           Independent background image attachment ID.
   * eyebrow           string / ''       Text above the event title.
   * intro             string / ''       Introductory text beneath the event details.
   * alert             string / ''       Optional message with a warning icon.
   * primary_label     string / ''       Primary button text; blank displays Register.
   * primary_url       string / ''       Primary link; blank targets available event tickets.
   * secondary_label   string / ''       Second button text; both text and link are required.
   * secondary_url     string / ''       Second button URL or section anchor.
   * ticket_notice     string / ''       Optional message above the native ticket forms.
   * coming_soon       bool / false      Replace header buttons with a coming-soon message.
   * entry_fee         string / ''       Display price, such as $35, From $35 or Free.
   * format_label      string / ''       Display wording for the tournament format.
   * rules_url         string / ''       Optional rules URL or section anchor.
   * 
   */
  public const LAYOUT_SETTINGS_KEY = '_bckfc_event_layout';

  public static function registerHooks(): void
  {
    // make sure WP knows about our metadata we are saving on these events
    add_action('init', [self::class, 'registerLayoutMetadata'], 100);

    // attached our function to Tribe's function that gets the template for single events so we can 
    // use our custom template when set.
    // this will pass our callback the path to the template Tribe selected at this step.
    add_filter('tribe_events_template_single-event.php', [self::class, 'selectEventTemplate']);

    // load our CSS (runs for every page)
    add_action('wp_enqueue_scripts', [self::class, 'enqueueEventStyles']);

    // hide the add to calendar link by intercepting it from Tribe and return an empty string when
    // using out custom template
    add_filter(
      'tribe_events_ical_single_event_links',
      [self::class, 'filterEventCalendarLinks'],
      30,
    );

    // allows tribe_events to use the Avada builder which has our components 
    add_filter('fusion_builder_allowed_post_types', [self::class, 'enableEventBuilder']);

    // allows us to hide Avada's title bar when using our custom template
    add_filter('awb_should_render_page_title_bar', [self::class, 'shouldRenderThemeTitle']);

    // hides the native event details section sidebar when using out custom template
    add_filter('avada_setting_get_ec_meta_layout', [self::class, 'selectEventDetailsPosition']);
  }

  public static function shouldRenderThemeTitle(bool $show): bool
  {
    return is_singular('tribe_events') && self::usesCustomTemplate(get_queried_object_id())
      ? false
      : $show;
  }

  public static function selectEventDetailsPosition($position)
  {
    return is_singular('tribe_events') && self::usesCustomTemplate(get_queried_object_id())
      ? 'below_content'
      : $position;
  }

  public static function registerLayoutMetadata(): void
  {
    register_post_meta('tribe_events', self::LAYOUT_SETTINGS_KEY, [
      'type' => 'object',
      'single' => true,
      'revisions_enabled' => true,
      'sanitize_callback' => [self::class, 'sanitizeLayoutSettings'],
    ]);
  }

  public static function sanitizeLayoutSettings($input): array
  {
    $input = is_array($input) ? $input : [];
    $settings = ['template' => ($input['template'] ?? '') === 'bckfc' ? 'bckfc' : ''];
    $settings['coming_soon'] = in_array($input['coming_soon'] ?? false, [true, 1, '1'], true);

    foreach (
      [
        'eyebrow',
        'intro',
        'alert',
        'primary_label',
        'secondary_label',
        'ticket_notice',
        'entry_fee',
        'format_label',
      ]
      as $key
    ) {
      $value = $input[$key] ?? '';
      $settings[$key] = is_string($value) ? sanitize_textarea_field($value) : '';
    }

    foreach (['primary_url', 'secondary_url', 'rules_url'] as $key) {
      $value = $input[$key] ?? '';
      $settings[$key] = is_string($value) ? esc_url_raw($value) : '';
    }

    foreach (['image', 'background'] as $key) {
      $id = is_scalar($input[$key] ?? '') ? absint($input[$key] ?? 0) : 0;
      $settings[$key] = $id && wp_attachment_is_image($id) ? $id : 0;
    }

    return $settings;
  }

  public static function getLayoutSettings(int $eventId): array
  {
    // Read the value list so WordPress's preview filter preserves our settings array.
    $values = get_post_meta($eventId, self::LAYOUT_SETTINGS_KEY, false);

    return self::sanitizeLayoutSettings($values[0] ?? []);
  }

  public static function usesCustomTemplate(int $eventId): bool
  {
    return get_post_type($eventId) === 'tribe_events' &&
      self::getLayoutSettings($eventId)['template'] === 'bckfc';
  }

  public static function enableEventBuilder(array $postTypes): array
  {
    return array_values(array_unique([...$postTypes, 'tribe_events']));
  }

  public static function selectEventTemplate($template)
  {
    if (!self::usesCustomTemplate(get_queried_object_id())) {
      return $template;
    }

    return dirname(__DIR__, 2) . '/templates/events/single-event.php';
  }

  public static function enqueueEventStyles(): void
  {
    if (!is_singular('tribe_events') || !self::usesCustomTemplate(get_queried_object_id())) {
      return;
    }

    $root = dirname(__DIR__, 2);

    wp_enqueue_style(
      'bckfc-fonts',
      'https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Source+Sans+3:ital,wght@0,300;0,400;0,600;0,700;1,400&display=swap',
      [],
      null,
    );
    wp_enqueue_style(
      'bckfc-design-tokens',
      plugins_url('assets/design-tokens.css', $root . '/bckfc-tools.php'),
      [],
      (string) filemtime($root . '/assets/design-tokens.css'),
    );
    wp_enqueue_style(
      'bckfc-typography',
      plugins_url('assets/typography.css', $root . '/bckfc-tools.php'),
      ['bckfc-fonts', 'bckfc-design-tokens'],
      (string) filemtime($root . '/assets/typography.css'),
    );
    wp_enqueue_style(
      'bckfc-buttons',
      plugins_url('assets/buttons.css', $root . '/bckfc-tools.php'),
      ['bckfc-typography'],
      (string) filemtime($root . '/assets/buttons.css'),
    );
    wp_enqueue_style(
      'bckfc-event-layout',
      plugins_url('assets/events/event-layout.css', $root . '/bckfc-tools.php'),
      ['bckfc-buttons'],
      (string) filemtime($root . '/assets/events/event-layout.css'),
    );
  }

  public static function renderEventHeader(int $eventId, bool $hasTicketPicker): void
  {
    $settings = self::getLayoutSettings($eventId);
    $titleWords = preg_split('/\s+/u', trim(get_the_title($eventId)), -1, PREG_SPLIT_NO_EMPTY);
    $titleLastWord = array_pop($titleWords) ?? '';
    $titleStart = implode(' ', $titleWords);

    $date = tribe_get_start_date($eventId, false, 'D, M j, Y');
    $endDate = tribe_get_end_date($eventId, false, 'D, M j, Y');
    $dateLabel = $date === $endDate ? $date : $date . ' – ' . $endDate;
    $fishingLocation = TournamentSettings::getEventLocations($eventId)['fishing'];
    $location = implode(
      ' · ',
      array_filter(
        [$fishingLocation['name'], $fishingLocation['location']],
        static fn(string $value): bool => $value !== '',
      ),
    );
    $background = wp_get_attachment_image_url($settings['background'], 'large');
    $primaryUrl = $settings['primary_url'] ?: '#bckfc-tickets';
    $primaryLabel = $settings['primary_label'] ?: 'Register';
    $eventHasEnded = tribe_is_past_event(get_post($eventId));
    $statusMessage = '';

    if ($eventHasEnded) {
      $statusMessage = 'This Event Has Passed.';
    } elseif ($settings['coming_soon']) {
      $statusMessage = 'Registration Coming Soon';
    }

    $showPrimary =
      $statusMessage === '' &&
      ($settings['primary_url'] !== '' || $hasTicketPicker);
    $showSecondary =
      $statusMessage === '' &&
      $settings['secondary_label'] !== '' &&
      $settings['secondary_url'] !== '';

    include dirname(__DIR__, 2) . '/templates/events/header.php';
  }

  public static function renderEventHighlights(int $eventId): void
  {
    $settings = self::getLayoutSettings($eventId);
    $registrationCloses = TournamentSettings::getRegistrationCloseDate($eventId);

    if (
      $settings['entry_fee'] === '' &&
      $settings['format_label'] === '' &&
      !$registrationCloses &&
      $settings['rules_url'] === ''
    ) {
      return;
    }

    include dirname(__DIR__, 2) . '/templates/events/highlights.php';
  }

  public static function renderRegistrationDeadline(): void
  {
    $date = TournamentSettings::getRegistrationCloseDate(get_the_ID());

    if (!$date) {
      return;
    }

    $label = wp_date('D, M j, Y \a\t g:i A T', $date->getTimestamp(), $date->getTimezone());

    echo '<div class="bckfc-registration-deadline">';
    include dirname(__DIR__, 2) . '/assets/icons/clock.svg';
    echo '<span>Registration closes <time datetime="' .
      esc_attr($date->format(DATE_ATOM)) .
      '">' .
      esc_html($label) .
      '</time></span>';
    echo '</div>';
  }

  public static function filterEventCalendarLinks(string $links): string
  {
    if (self::usesCustomTemplate(get_queried_object_id())) {
      return '';
    }

    return $links;
  }
}
