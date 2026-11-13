<?php
namespace BCKFC\Tools\Events;

/** Extends Tickets Plus through its ticket form and save hooks. */
final class TicketEditor
{
    public static function registerHooks(): void
    {
        add_action(
            'tribe_events_tickets_metabox_edit_main',
            [self::class, 'renderTicketTypeField'],
            10,
            3,
        );
        add_action(
            'event_tickets_after_save_ticket',
            [self::class, 'saveTicketTypeFromRequest'],
            10,
            4,
        );
    }

    public static function renderTicketTypeField($eventId, $ticketId, $type): void
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
            TournamentSettingsPanel::renderSelectOption($value, $label, $settings['purpose']);
        }

        echo '</select></div>';
    }

    public static function saveTicketTypeFromRequest(
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
            TournamentSettingsPanel::queueAdminErrorNotice(
                'The ticket was saved, but its BCKFC settings were not: ' . $e->getMessage(),
            );
        }
    }
}
