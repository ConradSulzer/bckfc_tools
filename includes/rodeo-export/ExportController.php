<?php
namespace BCKFC\Tools\RodeoExport;

final class ExportController
{
    public static function register(): void
    {
        foreach (
            [
                '/events' => 'GET',
                '/events/years' => 'GET',
                '/events/(?P<id>\d+)' => 'GET',
                '/events/(?P<id>\d+)/preview' => 'POST',
            ]
            as $route => $method
        ) {
            register_rest_route('bckfc-tools/v1', $route, [
                'methods' => $method,
                'args' =>
                    $route === '/events'
                        ? [
                            'year' => [
                                'type' => 'integer',
                                'required' => true,
                                'minimum' => 1900,
                                'maximum' => 9999,
                            ],
                        ]
                        : [],
                'permission_callback' => static fn() => current_user_can('manage_options'),
                'callback' => [self::class, 'handle'],
            ]);
        }
    }

    public static function handle(\WP_REST_Request $request)
    {
        if (!EventSource::available()) {
            return new \WP_Error(
                'dependencies',
                'Activate Event Tickets, Event Tickets Plus, and WooCommerce to use Rodeo Export.',
                ['status' => 503],
            );
        }

        if ($request->get_route() === '/bckfc-tools/v1/events/years') {
            return EventSource::eventYears();
        }

        $id = (int) $request['id'];

        if (!$id) {
            return EventSource::events((int) $request['year']);
        }

        if (get_post_type($id) !== 'tribe_events' || get_post_status($id) === 'trash') {
            return new \WP_Error('event', 'Event not found.', ['status' => 404]);
        }

        $tickets = EventSource::tickets($id);

        if ($request->get_method() === 'GET') {
            return [
                'tickets' => (object) $tickets,
            ];
        }

        try {
            $input = $request->get_json_params();
            $input = is_array($input) ? $input : [];
            $selected = $input['selectedTickets'] ?? [];

            if (!is_array($selected) || array_diff($selected, array_keys($tickets))) {
                throw new \InvalidArgumentException('Choose available tickets from this event.');
            }

            $selectedTickets = array_intersect_key($tickets, array_flip($selected));
            $pass = $input['seasonPass'] ?? null;
            $passTickets = [];
            $passEvent = 0;
            $passId = 0;

            if ($pass !== null) {
                $passEvent = (int) ($pass['eventId'] ?? 0);
                $passId = (int) ($pass['ticketId'] ?? 0);
                $year = substr(get_post_meta($id, '_EventStartDate', true), 0, 4);
                $eventIds = array_column(EventSource::events((int) $year), 'id');

                if (!in_array($passEvent, $eventIds, true)) {
                    throw new \InvalidArgumentException(
                        'Choose a season pass event in the tournament year.',
                    );
                }

                $available = EventSource::tickets($passEvent);

                if (!isset($available[$passId])) {
                    throw new \InvalidArgumentException('Choose a season pass ticket.');
                }

                $passTickets = [$passId => $available[$passId]];
                $input['seasonPass'] = [
                    'eventId' => (string) $passEvent,
                    'ticketId' => (string) $passId,
                    'onlyHolders' => ($pass['onlyHolders'] ?? false) === true,
                ];
                $input['rules'][$passId]['entry'] = true;
            }

            $included = $selectedTickets + $passTickets;

            if (!$included) {
                throw new \InvalidArgumentException('Select at least one ticket.');
            }

            $input['rules'] = array_intersect_key($input['rules'] ?? [], $included);

            foreach ($included as $ticketId => $_ticket) {
                $input['rules'][$ticketId] ??= [
                    'entry' => false,
                    'divisions' => [],
                    'checkboxes' => [],
                ];
            }

            $settings = ExportSettings::validate($input, $included);
            $records = EventSource::records($id, $passEvent === $id ? $included : $selectedTickets);

            if ($passEvent && $passEvent !== $id) {
                $records = array_merge($records, EventSource::records($passEvent, $passTickets));
            }

            $report = RodeoExport::build(
                $records,
                $settings['rules'],
                $settings['divisions'],
                $settings['seasonPass']['onlyHolders'] ?? false ? $passId : null,
            );
            $stream = fopen('php://temp', 'r+');
            RodeoExport::writeCsv($stream, $report, $settings['divisions']);
            rewind($stream);
            $csv = stream_get_contents($stream);
            fclose($stream);

            // CSV and preview represent exactly the same snapshot of attendees.
            return ['report' => $report, 'csv' => $csv];
        } catch (\InvalidArgumentException $error) {
            return new \WP_Error('mapping', $error->getMessage(), ['status' => 400]);
        }
    }
}
