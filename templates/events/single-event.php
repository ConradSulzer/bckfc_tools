<?php
use BCKFC\Tools\Events\BckfcEventLayout;
use BCKFC\Tools\Events\EventLoginRequirement;

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

    add_action('tribe_template_before_include:tickets/v2/tickets/notice', [
      EventLoginRequirement::class,
      'renderPurchaseInstructions',
    ], 10, 3);

    // Capture native after-content integrations, including tickets and RSVP.
    // Providers keep their configured hooks; this output is not exclusively tickets.
    do_action('tribe_events_single_event_after_the_content');
    do_action('tribe_events_single_event_before_the_meta');
    do_action('tribe_events_single_event_after_the_meta');
    remove_action('tribe_template_after_include:tickets/v2/tickets/title', [
      BckfcEventLayout::class,
      'renderRegistrationDeadline',
    ]);
    remove_action('tribe_template_before_include:tickets/v2/tickets/notice', [
      EventLoginRequirement::class,
      'renderPurchaseInstructions',
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

    $showLoginPrompt = $hasTicketPicker &&
      EventLoginRequirement::requiresLogin(get_the_ID()) &&
      !is_user_logged_in();

    // Load the header
    BckfcEventLayout::renderEventHeader(get_the_ID(), $hasTicketPicker);
    BckfcEventLayout::renderEventHighlights(get_the_ID());
    ?>
    <?= $contentMarkup ?>
    <?php if ($afterContentMarkup !== ''): ?>
      <section id="bckfc-tickets" class="bckfc bckfc-event-section bckfc-event-section--tan bckfc-event-tickets<?= $showLoginPrompt ? ' bckfc-registration-gate' : '' ?>" aria-label="Event Tickets">
        <?php if ($showLoginPrompt): ?>
          <div class="bckfc-registration-gate__content" inert aria-hidden="true">
        <?php endif; ?>
        <?php if ($settings['ticket_notice']): ?>
          <div class="bckfc-event-ticket-notice">
            <?= nl2br(esc_html($settings['ticket_notice'])) ?>
          </div>
        <?php endif; ?>

        <?= $afterContentMarkup ?>
        <?php if ($showLoginPrompt): ?>
          </div>
          <div class="bckfc-registration-gate__prompt"><div>
            <p class="bckfc-registration-instructions"><?= esc_html(EventLoginRequirement::PURCHASE_INSTRUCTIONS) ?></p>
            <a class="bckfc-button" href="<?= esc_url(wp_login_url(get_permalink())) ?>">Log In to Purchase Tickets</a>
          </div></div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  <?php endwhile; ?>
</div>