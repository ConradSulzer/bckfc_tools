<?php
use BCKFC\Tools\Events\TournamentSettings;

defined('ABSPATH') || exit();
?>
<section class="bckfc bckfc-event-section bckfc-event-section--ink bckfc-tournament-timeline">
    <div class="bckfc-tournament-timeline__columns">
        <?php if ($times): ?>
            <div class="bckfc-tournament-timeline__schedule">
                <?php if ($heading !== ''): ?>
                    <h2><?= esc_html($heading) ?></h2>
                <?php endif; ?>
                <ol class="bckfc-tournament-timeline__times">
                    <?php foreach ($times as $key => $time): ?>
                        <li>
                            <time datetime="<?= esc_attr($time) ?>"><?= esc_html(
    DateTimeImmutable::createFromFormat('!H:i', $time)->format('g:i A'),
) ?></time>
                            <span><?= esc_html(TournamentSettings::TIME_FIELDS[$key]) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        <?php endif; ?>

        <?php if ($locations): ?>
            <div class="bckfc-tournament-timeline__locations">
                <?php foreach ($locations as $key => $location): ?>
                    <?php $locationTitle = implode(
                        ' · ',
                        array_filter([$location['name'], $location['location']]),
                    ); ?>
                    <div class="bckfc-tournament-location">
                        <h2><?php echo $key === 'fishing'
                            ? 'Launch Location'
                            : 'Weigh-In / Awards'; ?></h2>
                        <?php include dirname(__DIR__, 2) .
                            '/assets/icons/' .
                            ($key === 'fishing' ? 'map-pin.svg' : 'trophy.svg'); ?>
                        <?php if ($locationTitle !== ''): ?>
                            <h3><?= esc_html($locationTitle) ?></h3>
                        <?php endif; ?>
                        <?php if ($location['address'] !== ''): ?>
                            <p class="bckfc-tournament-location__address"><?= esc_html(
                                $location['address'],
                            ) ?></p>
                            <a href="<?= esc_url(
                                'https://www.google.com/maps/dir/?api=1&destination=' .
                                    rawurlencode($location['address']),
                            ) ?>">Get Directions</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($details !== ''): ?>
        <div class="bckfc-tournament-timeline__details"><?= $details ?></div>
    <?php endif; ?>
</section>
