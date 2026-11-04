<?php
namespace BCKFC\Tools;

/** Event Tickets Plus / WooCommerce data access; never modifies tickets or orders. */
final class EventSource
{
    public static function available(): bool
    {
        return class_exists('Tribe__Tickets__Tickets') &&
            class_exists('Tribe__Tickets_Plus__Meta') &&
            function_exists('wc_get_order');
    }

    public static function eventYears(): array
    {
        global $wpdb;

        $years = $wpdb->get_col(
            "SELECT DISTINCT YEAR(meta.meta_value)
             FROM {$wpdb->postmeta} meta
             JOIN {$wpdb->posts} posts ON posts.ID = meta.post_id
             WHERE meta.meta_key = '_EventStartDate'
               AND posts.post_type = 'tribe_events'
               AND posts.post_status IN ('publish', 'draft', 'private', 'future')",
        );
        $currentYear = (int) wp_date('Y');
        $years = array_values(
            array_unique(array_merge([$currentYear], array_filter(array_map('intval', $years)))),
        );
        rsort($years);

        return ['currentYear' => $currentYear, 'years' => $years];
    }

    public static function events(int $year): array
    {
        $events = get_posts([
            'post_type' => 'tribe_events',
            'post_status' => ['publish', 'draft', 'private', 'future'],
            'numberposts' => -1,
            'meta_key' => '_EventStartDate',
            'meta_value' => [
                sprintf('%04d-01-01 00:00:00', $year),
                sprintf('%04d-12-31 23:59:59', $year),
            ],
            'meta_compare' => 'BETWEEN',
            'meta_type' => 'DATETIME',
            'orderby' => 'meta_value',
            'order' => 'DESC',
        ]);

        return array_map(
            static fn($event) => [
                'id' => $event->ID,
                'name' => html_entity_decode($event->post_title, ENT_QUOTES, 'UTF-8'),
                'date' => substr(get_post_meta($event->ID, '_EventStartDate', true), 0, 10),
            ],
            $events,
        );
    }

    public static function tickets(int $eventId): array
    {
        $tickets = [];

        foreach (\Tribe__Tickets__Tickets::get_all_event_tickets($eventId) as $ticket) {
            $id = (int) $ticket->ID;
            // This first version explicitly supports the club's WooCommerce tickets.
            if ((int) get_post_meta($id, '_tribe_wooticket_for_event', true) !== $eventId) {
                continue;
            }

            $checkboxes = [];
            $fields = get_post_meta($id, '_tribe_tickets_meta', true);

            foreach (is_array($fields) ? $fields : [] as $field) {
                if (($field['type'] ?? '') !== 'checkbox') {
                    continue;
                }

                foreach ($field['extra']['options'] ?? [] as $option) {
                    if (trim($option) === '') {
                        continue;
                    }

                    $key = $field['slug'] . '_' . md5(sanitize_title($option));
                    $checkboxes[$key] = [
                        'label' => $field['label'] . ': ' . $option,
                        'legacy_key' => $field['slug'] . '_' . sanitize_title($option),
                    ];
                }
            }

            $tickets[$id] = [
                'name' => html_entity_decode(get_the_title($id), ENT_QUOTES, 'UTF-8'),
                'checkboxes' => $checkboxes,
            ];
        }

        return $tickets;
    }

    public static function records(int $eventId, array $tickets): array
    {
        $ids = get_posts([
            'post_type' => 'tribe_wooticket',
            'post_status' => array_values(get_post_stati()),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'meta_key' => '_tribe_wooticket_event',
            'meta_value' => $eventId,
        ]);
        $records = [];
        $orders = [];

        foreach ($ids as $id) {
            $ticketId = (int) get_post_meta($id, '_tribe_wooticket_product', true);
            if (!isset($tickets[$ticketId])) {
                continue;
            }

            $orderId = (int) get_post_meta($id, '_tribe_wooticket_order', true);

            if (!array_key_exists($orderId, $orders)) {
                $orders[$orderId] = wc_get_order($orderId);
            }

            $order = $orders[$orderId];
            $reason = '';

            if (get_post_status($id) !== 'publish') {
                $reason = 'Attendee is not published (including trash)';
            } elseif (!$order) {
                $reason = 'Missing order';
            } elseif (!in_array($order->get_status(), ['processing', 'completed'], true)) {
                $reason = 'Order status: ' . $order->get_status();
            }

            $meta = get_post_meta($id, '_tribe_tickets_meta', true);
            $meta = is_array($meta) ? $meta : [];
            $checked = [];

            foreach ($tickets[$ticketId]['checkboxes'] as $key => $definition) {
                if (!empty($meta[$key]) || !empty($meta[$definition['legacy_key']])) {
                    $checked[] = $key;
                }
            }

            $warnings = [];

            if ($order && (float) $order->get_total_refunded() > 0 && $reason === '') {
                $warnings[] = 'Order has a refund; verify this attendee still qualifies.';
            }

            $records[] = [
                'id' => (int) $id,
                'ticket_id' => $ticketId,
                'ticket' => $tickets[$ticketId]['name'],
                'name' => (string) get_post_meta($id, '_tribe_tickets_full_name', true),
                'email' => (string) get_post_meta($id, '_tribe_tickets_email', true),
                'phone' => (string) ($meta['personal-cellphone'] ?? ''),
                'emergency' => (string) ($meta['emergency-contact-phone'] ?? ''),
                'checked' => $checked,
                'excluded' => $reason,
                'warnings' => $warnings,
            ];
        }

        return $records;
    }
}
