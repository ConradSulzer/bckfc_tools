<?php
defined('ABSPATH') || exit();

$iconDirectory = dirname(__DIR__, 2) . '/assets/icons/';
$heroClass = 'bckfc bckfc-event-hero';

if ($settings['image']) {
    $heroClass .= ' bckfc-event-hero--with-image';
}
?>
<header class="<?= esc_attr($heroClass) ?>">
    <div class="bckfc-event-hero__backdrop" aria-hidden="true">
        <?php if ($background): ?>
            <div
                class="bckfc-event-hero__background"
                style="background-image: url('<?= esc_url($background) ?>')"
            ></div>
        <?php endif; ?>
    </div>

    <div class="bckfc-event-hero__content">
        <?php if ($settings['alert']): ?>
            <div class="bckfc-event-alert">
                <?php include $iconDirectory . 'triangle-alert.svg'; ?>
                <span><?= esc_html($settings['alert']) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($settings['eyebrow']): ?>
            <p class="bckfc-event-eyebrow"><?= esc_html($settings['eyebrow']) ?></p>
        <?php endif; ?>

        <h1>
            <?= esc_html($titleStart) ?>
            <span class="bckfc-event-title-accent"><?= esc_html($titleLastWord) ?></span>
        </h1>

        <div class="bckfc-event-facts">
            <span class="bckfc-event-fact">
                <?php include $iconDirectory . 'calendar-days.svg'; ?>
                <span><?= esc_html($dateLabel) ?></span>
            </span>

            <?php if ($location): ?>
                <span class="bckfc-event-fact">
                    <?php include $iconDirectory . 'map-pin.svg'; ?>
                    <span><?= esc_html($location) ?></span>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($settings['intro']): ?>
            <p class="bckfc-event-intro"><?= nl2br(esc_html($settings['intro'])) ?></p>
        <?php endif; ?>

        <?php if ($statusMessage !== ''): ?>
            <p class="bckfc-event-status"><?= esc_html($statusMessage) ?></p>
        <?php elseif ($showPrimary || $showSecondary): ?>
            <div class="bckfc-event-actions">
                <?php if ($showPrimary): ?>
                    <a class="bckfc-button" href="<?= esc_url($primaryUrl) ?>">
                        <?= esc_html($primaryLabel) ?>
                        <?php include $iconDirectory . 'arrow-right.svg'; ?>
                    </a>
                <?php endif; ?>

                <?php if ($showSecondary): ?>
                    <a class="bckfc-button bckfc-button--outline" href="<?= esc_url(
                        $settings['secondary_url'],
                    ) ?>">
                        <?= esc_html($settings['secondary_label']) ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($settings['image']): ?>
        <div class="bckfc-event-hero__art">
            <?php echo wp_get_attachment_image($settings['image'], 'large', false, [
                'loading' => 'eager',
                'class' => 'bckfc-event-image',
            ]); ?>
        </div>
    <?php endif; ?>
</header>
