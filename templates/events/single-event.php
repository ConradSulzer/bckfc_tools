<?php
use BCKFC\Tools\Events\BckfcEventLayout;

defined('ABSPATH') || exit();
?>
<div id="tribe-events-content" class="tribe-events-single bckfc-event-page">
  <?php tribe_the_notices(); ?>
  <?php while (have_posts()): ?>
    <?php the_post(); ?>

    <?php // Load the description (middle content) ?>
    <?php ob_start(); ?>
    <?php do_action('tribe_events_single_event_before_the_content'); ?>
    <div class="tribe-events-single-event-description tribe-events-content entry-content bckfc-event-body">
      <?php the_content(); ?>
    </div>
    <?php
    $contentMarkup = ob_get_clean();

    // Load the tickets section
    ob_start();
    add_action('tribe_template_after_include:tickets/v2/tickets/title', [
      BckfcEventLayout::class,
      'renderRegistrationDeadline',
    ]);

    // Capture native after-content integrations, including tickets and RSVP.
    // Providers keep their configured hooks; this output is not exclusively tickets.
    do_action('tribe_events_single_event_after_the_content');
    do_action('tribe_events_single_event_before_the_meta');
    do_action('tribe_events_single_event_after_the_meta');
    remove_action('tribe_template_after_include:tickets/v2/tickets/title', [
      BckfcEventLayout::class,
      'renderRegistrationDeadline',
    ]);
    $afterContentMarkup = trim(ob_get_clean());
    $settings = BckfcEventLayout::getLayoutSettings(get_the_ID());

    // Other integrations can produce output here without rendering a picker.
    $ticketMarkup = new WP_HTML_Tag_Processor($afterContentMarkup);
    $hasTicketPicker = false;

    while ($ticketMarkup->next_tag()) {
      if (
        $ticketMarkup->has_class('tribe-tickets__tickets-form') ||
        $ticketMarkup->has_class('tribe-tickets__rsvp-wrapper')
      ) {
        $hasTicketPicker = true;
        break;
      }
    }

    // Load the header
    BckfcEventLayout::renderEventHeader(get_the_ID(), $hasTicketPicker);
    BckfcEventLayout::renderEventHighlights(get_the_ID());
    ?>
    <?= $contentMarkup ?>
    <?php if ($afterContentMarkup !== ''): ?>
      <section id="bckfc-tickets" class="bckfc bckfc-event-section bckfc-event-section--tan bckfc-event-tickets" aria-label="Event Tickets">
        <?php if ($settings['ticket_notice']): ?>
          <div class="bckfc-event-ticket-notice">
            <?= nl2br(esc_html($settings['ticket_notice'])) ?>
          </div>
        <?php endif; ?>

        <?= $afterContentMarkup ?>
      </section>
    <?php endif; ?>
  <?php endwhile; ?>
</div>