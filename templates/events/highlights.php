<?php
defined('ABSPATH') || exit();

$iconDirectory = dirname(__DIR__, 2) . '/assets/icons/';
?>
<section class="bckfc bckfc-event-highlights" aria-label="Event Highlights">
    <?php if ($settings['entry_fee'] !== ''): ?>
        <div class="bckfc-event-highlight">
            <?php include $iconDirectory . 'tickets.svg'; ?>
            <span class="bckfc-event-highlight__value"><?= esc_html(
                $settings['entry_fee'],
            ) ?></span>
            <span class="bckfc-event-highlight__label">Entry Fee</span>
        </div>
    <?php endif; ?>

    <?php if ($settings['format_label'] !== ''): ?>
        <div class="bckfc-event-highlight">
            <?php include $iconDirectory . 'fish.svg'; ?>
            <span class="bckfc-event-highlight__value"><?= esc_html(
                $settings['format_label'],
            ) ?></span>
            <span class="bckfc-event-highlight__label">Format</span>
        </div>
    <?php endif; ?>

    <?php if ($registrationCloses): ?>
        <div class="bckfc-event-highlight">
            <?php include $iconDirectory . 'clock.svg'; ?>
            <time class="bckfc-event-highlight__value" datetime="<?= esc_attr(
                $registrationCloses->format(DATE_ATOM),
            ) ?>">
                <span><?= esc_html(
                    wp_date(
                        'M j',
                        $registrationCloses->getTimestamp(),
                        $registrationCloses->getTimezone(),
                    ),
                ) ?></span>
                <span><?= esc_html(
                    wp_date(
                        'g:i A',
                        $registrationCloses->getTimestamp(),
                        $registrationCloses->getTimezone(),
                    ),
                ) ?></span>
            </time>
            <span class="bckfc-event-highlight__label">Registration Closes</span>
        </div>
    <?php endif; ?>

    <?php if ($settings['rules_url'] !== ''): ?>
        <a class="bckfc-event-highlight bckfc-event-highlight--link" href="<?= esc_url(
            $settings['rules_url'],
        ) ?>">
            <?php include $iconDirectory . 'clipboard-list.svg'; ?>
            <span class="bckfc-event-highlight__value">Read the Rules <?php include $iconDirectory .
                'arrow-right.svg'; ?></span>
            <span class="bckfc-event-highlight__label">Know What's Up</span>
        </a>
    <?php endif; ?>
</section>
