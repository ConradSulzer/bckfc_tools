import { useEffect, useState } from 'react';
import { ClipboardList } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { getEvents, getEventYears } from './api';
import type { Event } from './types';
import { EventAnglerExport } from './EventAnglerExport';
import { clearExpiredSetups } from './storage';

const selectClass =
  'h-9 w-full rounded-md border bg-background px-3 text-sm text-foreground focus-visible:outline-primary';

export function RodeoAnglerExport() {
  const [years, setYears] = useState<number[]>([]);
  const [year, setYear] = useState<number | null>(null);
  const [events, setEvents] = useState<Event[]>([]);
  const [eventId, setEventId] = useState(
    () => new URL(location.href).searchParams.get('event') || '',
  );
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let current = true;

    clearExpiredSetups();

    getEventYears()
      .then(({ currentYear, years }) => {
        if (!current) return;

        const requestedYear = Number(new URL(location.href).searchParams.get('year'));
        setYears(years);
        setYear(years.includes(requestedYear) ? requestedYear : currentYear);
      })
      .catch((error) => {
        if (!current) return;

        setError(error.message);
        setLoading(false);
      });

    return () => {
      current = false;
    };
  }, []);

  useEffect(() => {
    if (year === null) return;

    let current = true;
    setLoading(true);
    setError('');
    setEvents([]);

    getEvents(year)
      .then((events) => {
        if (!current) return;

        setEvents(events);
        const url = new URL(location.href);
        const selectedId = url.searchParams.get('event') || '';
        const validId = events.some((event) => String(event.id) === selectedId) ? selectedId : '';
        setEventId(validId);
        url.searchParams.set('year', String(year));

        if (!validId) url.searchParams.delete('event');

        history.replaceState(null, '', url);
      })
      .catch((error) => {
        if (current) setError(error.message);
      })
      .finally(() => {
        if (current) setLoading(false);
      });

    return () => {
      current = false;
    };
  }, [year]);

  function changeYear(nextYear: number) {
    setYear(nextYear);
    setEventId('');
    setEvents([]);
    setLoading(true);
    const url = new URL(location.href);
    url.searchParams.set('year', String(nextYear));
    url.searchParams.delete('event');
    history.replaceState(null, '', url);
  }

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-xl font-semibold">Rodeo Angler Exporter</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Export angler's in a format that Rodeo can ingest. Choose which tickets are relevant for
          entry into an event. Define your divisions. Then map those tickets or checkboxes from
          attendee-information to your divisions. Exports a CSV ready to pull everything into Rodeo.
        </p>
      </div>
      <Card className="space-y-3">
        <div className="grid gap-3 sm:grid-cols-[8rem_minmax(0,1fr)]">
          <div className="space-y-2">
            <label htmlFor="event-year" className="block text-sm font-semibold">
              Year
            </label>
            <select
              id="event-year"
              className={selectClass}
              value={year ?? ''}
              disabled={year === null}
              onChange={(event) => changeYear(Number(event.target.value))}
            >
              {year === null && <option value="">Loading…</option>}
              {years.map((value) => (
                <option key={value} value={value}>
                  {value}
                </option>
              ))}
            </select>
          </div>
          <div className="space-y-2">
            <label htmlFor="event" className="block text-sm font-semibold">
              Tournament event
            </label>
            <select
              id="event"
              className={selectClass}
              value={eventId}
              disabled={loading}
              onChange={(e) => {
                const id = e.target.value;
                setEventId(id);
                const url = new URL(location.href);

                if (id) url.searchParams.set('event', id);
                else url.searchParams.delete('event');
                history.replaceState(null, '', url);
              }}
            >
              <option value="">{loading ? 'Loading events…' : 'Choose an event'}</option>
              {events.map((e) => (
                <option key={e.id} value={e.id}>
                  {e.date} · {e.name}
                </option>
              ))}
            </select>
          </div>
        </div>
        {error && (
          <p role="alert" className="text-negative">
            {error}
          </p>
        )}
        {!loading && !error && !events.length && (
          <p className="text-muted-foreground">No events found for {year}.</p>
        )}
      </Card>
      {!loading && !error && eventId ? (
        <EventAnglerExport key={eventId} eventId={eventId} events={events} />
      ) : (
        <div className="rounded-xl border border-dashed p-12 text-center text-muted-foreground">
          <ClipboardList className="mx-auto mb-3 size-8" />
          <p>Choose an event to discover its tickets and registration options.</p>
        </div>
      )}
    </div>
  );
}
