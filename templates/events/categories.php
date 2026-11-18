<?php defined('ABSPATH') || exit(); ?>
<section class="bckfc bckfc-event-categories">
    <?php if ($heading !== ''): ?>
        <h2><?= esc_html($heading) ?></h2>
    <?php endif; ?>
    <table class="bckfc-event-categories__table">
        <thead>
            <tr><th scope="col">Category</th><th scope="col">Details</th><th scope="col">Payout / Prize</th></tr>
        </thead>
        <tbody><?= $rows ?></tbody>
    </table>
    <?php if ($footer !== ''): ?>
        <div class="bckfc-event-categories__footer">
            <?= wpautop(wp_kses_post($footer)) ?>
        </div>
    <?php endif; ?>
</section>
