<div class="bckfc-design">
    <table class="bckfc-size-limits" aria-label="Tournament Size Limits" role="table">
        <thead role="rowgroup">
            <tr role="row">
                <th scope="col" class="bckfc-label" role="columnheader">Species</th>
                <th scope="col" class="bckfc-label" role="columnheader">Minimum</th>
                <th scope="col" class="bckfc-label" role="columnheader">Maximum</th>
            </tr>
        </thead>
        <tbody role="rowgroup">
            <?php foreach ($limits as $limit): ?>
                <tr role="row">
                    <th scope="row" role="rowheader"><?php echo esc_html($limit['species']); ?></th>
                    <td role="cell"><span class="bckfc-size-limits__mobile-label bckfc-label" aria-hidden="true">Minimum</span><?php echo esc_html($limit['minimum']); ?></td>
                    <td role="cell"><span class="bckfc-size-limits__mobile-label bckfc-label" aria-hidden="true">Maximum</span><?php echo esc_html($limit['maximum']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
