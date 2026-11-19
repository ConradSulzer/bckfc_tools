<?php
namespace BCKFC\Tools\Events;

/** BCKFC ticket types stored on existing WooCommerce products. */
final class TicketSettings
{
    public const TYPES = [
        'entry' => 'Entry',
        'youth_entry' => 'Youth Entry',
        'calcutta' => 'Calcutta',
        'raffle' => 'Raffle',
        'season_pass' => 'Season Pass',
        'food_fun' => 'Food & Fun',
        'merchandise' => 'Merchandise',
        'other' => 'Other',
    ];

    public static function getTicketSettings(int $id): array
    {
        return [
            'purpose' => (string) get_post_meta($id, '_bckfc_ticket_purpose', true) ?: 'other',
        ];
    }

    public static function saveTicketSettings(int $id, array $input): void
    {
        $purpose = $input['purpose'] ?? 'other';

        if (!is_string($purpose) || !isset(self::TYPES[$purpose])) {
            throw new \InvalidArgumentException('Choose a valid BCKFC Type.');
        }

        update_post_meta($id, '_bckfc_ticket_purpose', $purpose);

        if (isset($input['sold_individually'])) {
            $product = wc_get_product($id);
            $product->set_sold_individually(!empty($input['sold_individually']));
            $product->save();
        }
    }
}
