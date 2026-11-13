<?php
use BCKFC\Tools\Events\TournamentSettings;

defined('ABSPATH') || exit();
?>
<section class="bckfc bckfc-tournament-timeline">
    <?php if ($heading !== ''): ?>
        <h2><?= esc_html($heading) ?></h2>
    <?php endif; ?>

    <div class="bckfc-tournament-timeline__columns">
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

        <?php if ($details !== ''): ?>
            <div class="bckfc-tournament-timeline__details"><?= $details ?></div>
        <?php endif; ?>
    </div>
</section>
