<?php
namespace BCKFC\Tools\Events;

/** Extends Tickets Plus through its ticket form and save hooks. */
final class TicketEditor
{
    public static function registerHooks(): void
    {
        add_action(
            'tribe_events_tickets_metabox_edit_main',
            [self::class, 'renderTicketSettingsFields'],
            10,
            3,
        );
        add_action(
            'event_tickets_after_save_ticket',
            [self::class, 'saveTicketSettingsFromRequest'],
            10,
            4,
        );
    }

    public static function renderTicketSettingsFields($eventId, $ticketId, $type): void
    {
        $eventId = (int) $eventId;
        $ticketId = (int) $ticketId;

        if (get_post_type($eventId) !== 'tribe_events' || $type === 'rsvp') {
            return;
        }

        if ($ticketId && get_post_type($ticketId) !== 'product') {
            return;
        }

        $settings = TicketSettings::getTicketSettings($ticketId);

        wp_nonce_field('bckfc_ticket_' . $eventId, 'bckfc_ticket_nonce', false);

        echo '<div class="input_block"><label class="ticket_form_label ticket_form_left" for="bckfc-ticket-purpose">BCKFC Type:</label><select class="ticket_field ticket_form_right" id="bckfc-ticket-purpose" name="bckfc_ticket[purpose]">';

        foreach (TicketSettings::TYPES as $value => $label) {
            TournamentSettingsEditor::renderSelectOption($value, $label, $settings['purpose']);
        }

        echo '</select></div>';

        $product = $ticketId ? wc_get_product($ticketId) : false;
        $soldIndividually = $product && $product->is_sold_individually();

        echo '<div class="input_block"><span class="ticket_form_label ticket_form_left">Purchase Limit:</span><label class="ticket_form_right" for="bckfc-ticket-sold-individually">';
        echo '<input type="hidden" name="bckfc_ticket[sold_individually]" value="0">';
        echo '<input type="checkbox" id="bckfc-ticket-sold-individually" name="bckfc_ticket[sold_individually]" value="1" ' .
            checked($soldIndividually, true, false) .
            '> Limit to One per Order</label></div>';
    }

    public static function saveTicketSettingsFromRequest(
        $eventId,
        $ticket,
        array $data,
        string $provider,
    ): void {
        $eventId = (int) $eventId;

        if (
            $provider !== 'Tribe__Tickets_Plus__Commerce__WooCommerce__Main' ||
            get_post_type($eventId) !== 'tribe_events' ||
            !current_user_can('edit_post', $eventId)
        ) {
            return;
        }

        if (
            !isset($data['bckfc_ticket_nonce']) ||
            !is_string($data['bckfc_ticket_nonce']) ||
            !wp_verify_nonce($data['bckfc_ticket_nonce'], 'bckfc_ticket_' . $eventId)
        ) {
            return;
        }

        if (!isset($data['bckfc_ticket']) || !is_array($data['bckfc_ticket'])) {
            return;
        }

        try {
            TicketSettings::saveTicketSettings((int) $ticket->ID, $data['bckfc_ticket']);
        } catch (\InvalidArgumentException $e) {
            TournamentSettingsEditor::queueAdminErrorNotice(
                'The ticket was saved, but its BCKFC settings were not: ' . $e->getMessage(),
            );
        }
    }
}
