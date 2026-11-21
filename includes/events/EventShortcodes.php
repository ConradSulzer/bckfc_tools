<?php
namespace BCKFC\Tools\Events;

/** Values and size-limit table from the event containing the content. */
final class EventShortcodes
{
  public static function registerHooks(): void
  {
    add_shortcode('bckfc_event_time', [self::class, 'renderEventTime']);
    add_shortcode('bckfc_event_location', [self::class, 'renderEventLocation']);
    add_shortcode('bckfc_event_date', [self::class, 'renderEventDate']);
    add_shortcode('bckfc_registration_closes', [self::class, 'renderRegistrationCloseDate']);
    add_shortcode('bckfc_size_limits', [self::class, 'renderSizeLimitsTable']);
  }

  public static function renderSizeLimitsTable(): string
  {
    $eventId = get_the_ID();

    if (get_post_type($eventId) !== 'tribe_events') {
      return '';
    }

    $limits = TournamentSettings::getEventSizeLimits((int) $eventId);

    if (!$limits) {
      return '';
    }

    ob_start();
    include dirname(__DIR__, 2) . '/templates/events/size-limits.php';

    return ob_get_clean();
  }

  public static function renderEventDate(): string
  {
    $eventId = get_the_ID();

    if (get_post_type($eventId) !== 'tribe_events') {
      return '';
    }

    return esc_html(tribe_get_start_date($eventId, false, 'F j, Y'));
  }

  public static function renderRegistrationCloseDate($attributes = []): string
  {
    $eventId = get_the_ID();

    if (get_post_type($eventId) !== 'tribe_events') {
      return '';
    }

    $date = TournamentSettings::getRegistrationCloseDate($eventId);

    if (!$date) {
      return '';
    }

    $attributes = shortcode_atts(['format' => 'long'], $attributes);
    $format = $attributes['format'] === 'short' ? 'M j, Y \\A\\T g:i A' : 'F j, Y \\a\\t g:i A';

    return esc_html(wp_date($format, $date->getTimestamp(), $date->getTimezone()));
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
