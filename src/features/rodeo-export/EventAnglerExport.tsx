import { useEffect, useState } from 'react';
import { Download, ArrowRight, RefreshCw, Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardTitle, MetricCard } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Table } from '@/components/ui/table';
import { getEventTickets, previewAnglerExport } from './api';
import type { Settings, Ticket, Rule, Preview, Event } from './types';
import { emptySettings, loadSetup, saveSetup, removeSetup } from './storage';

const blankRule = (): Rule => ({ entry: false, divisions: [], checkboxes: {} });

function DivisionChoices({
  divisions,
  selected,
  onChange,
  label,
}: {
  divisions: string[];
  selected: string[];
  onChange: (values: string[]) => void;
  label: string;
}) {
  return (
    <fieldset className="flex flex-wrap gap-2">
      <legend className="sr-only">{label}</legend>
      {divisions.map((division) => (
        <label
          key={division}
          className="flex cursor-pointer items-center gap-2 rounded-md border bg-background px-3 py-2 text-sm"
        >
          <Checkbox
            aria-label={`${label}: ${division}`}
            checked={selected.includes(division)}
            onCheckedChange={(checked) =>
              onChange(checked ? [...selected, division] : selected.filter((v) => v !== division))
            }
          />
          {division}
        </label>
      ))}
    </fieldset>
  );
}

export function EventAnglerExport({ eventId, events }: { eventId: string; events: Event[] }) {
  const [tickets, setTickets] = useState<Record<string, Ticket>>({});
  const [settings, setSettings] = useState<Settings>(() => loadSetup(eventId));
  const [storageError, setStorageError] = useState('');
  const [passTickets, setPassTickets] = useState<Record<string, Ticket>>({});
  const [passLoading, setPassLoading] = useState(false);
  const [passError, setPassError] = useState('');
  const [division, setDivision] = useState('');
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [preview, setPreview] = useState<Preview | null>(null);
  const [issuesOnly, setIssuesOnly] = useState(false);

  useEffect(() => {
    let current = true;
    getEventTickets(eventId)
      .then((data) => {
        if (current) {
          setTickets(data.tickets);
        }
      })
      .catch((e) => {
        if (current) setError(e.message);
      })
      .finally(() => {
        if (current) setLoading(false);
      });

    return () => {
      current = false;
    };
  }, [eventId]);

  const passEventId = settings.seasonPass?.eventId || '';

  useEffect(() => {
    let current = true;
    setPassTickets({});
    setPassError('');
    setPassLoading(Boolean(passEventId));

    if (!passEventId) return;

    getEventTickets(passEventId)
      .then((data) => {
        if (current) setPassTickets(data.tickets);
      })
      .catch((error) => {
        if (current) setPassError(error.message);
      })
      .finally(() => {
        if (current) setPassLoading(false);
      });

    return () => {
      current = false;
    };
  }, [passEventId]);

  function selectTicket(id: string, selected: boolean) {
    change({
      ...settings,
      selectedTickets: selected
        ? [...settings.selectedTickets, id]
        : settings.selectedTickets.filter((ticketId) => ticketId !== id),
    });
  }

  function change(next: Settings) {
    setSettings(next);
    setStorageError(
      saveSetup(eventId, next)
        ? ''
        : 'Browser storage is unavailable. This setup will only last while the page stays open.',
    );
    setPreview(null);
    setError('');
  }

  function resetSetup() {
    setSettings(emptySettings());
    setPreview(null);
    setDivision('');
    setIssuesOnly(false);
    setError('');
    setStorageError(
      removeSetup(eventId)
        ? ''
        : 'Unable to clear browser storage. The setup has been reset for this page.',
    );
  }

  function updateRule(id: string, values: Partial<Rule>) {
    change({
      ...settings,
      rules: { ...settings.rules, [id]: { ...(settings.rules[id] || blankRule()), ...values } },
    });
  }

  function addDivision() {
    const name = division.trim();

    if (!name) return;

    if (settings.divisions.some((v) => v.toLowerCase() === name.toLowerCase())) {
      setError('That division already exists.');
      return;
    }

    change({ ...settings, divisions: [...settings.divisions, name] });
    setDivision('');
  }

  function removeDivision(name: string) {
    const rules = Object.fromEntries(
      Object.entries(settings.rules).map(([id, rule]) => [
        id,
        {
          ...rule,
          divisions: rule.divisions.filter((v) => v !== name),
          checkboxes: Object.fromEntries(
            Object.entries(rule.checkboxes).map(([key, values]) => [
              key,
              values.filter((v) => v !== name),
            ]),
          ),
        },
      ]),
    );

    change({ ...settings, divisions: settings.divisions.filter((v) => v !== name), rules });
  }

  async function generate() {
    setBusy(true);
    setError('');
    setPreview(null);

    try {
      setPreview(await previewAnglerExport(eventId, settings));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unable to load attendees.');
    } finally {
      setBusy(false);
    }
  }

  function download() {
    if (!preview?.csv) return;

    const eventName = events.find((event) => String(event.id) === eventId)?.name || eventId;
    const eventSlug =
      eventName
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '') || eventId;
    const timestamp = new Date()
      .toISOString()
      .replace(/:/g, '-')
      .replace(/\.\d{3}Z$/, 'Z');
    const url = URL.createObjectURL(new Blob([preview.csv], { type: 'text/csv;charset=utf-8;' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `rodeo-${eventSlug}-anglers-${timestamp}.csv`;
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  if (loading)
    return (
      <p role="status" className="text-muted-foreground">
        Loading tickets…
      </p>
    );

  const warningCount = preview?.report.anglers.filter((a) => a.warnings.length > 0).length || 0;
  const visible =
    preview?.report.anglers.filter((a) => !issuesOnly || a.warnings.length || a.errors.length) ||
    [];
  const mappingTickets = Object.fromEntries(
    Object.entries(tickets).filter(([id]) => settings.selectedTickets.includes(id)),
  );
  const passTicketId = settings.seasonPass?.ticketId || '';

  if (passTicketId && passTickets[passTicketId]) {
    mappingTickets[passTicketId] = passTickets[passTicketId];
  }

  const hasEntry =
    Boolean(passTicketId) || settings.selectedTickets.some((id) => settings.rules[id]?.entry);

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-xs text-muted-foreground">
          Setup is remembered in this browser for six months after your last edit.
        </p>
        <Button variant="outline" disabled={busy} onClick={resetSetup}>
          Reset setup
        </Button>
      </div>
      {storageError && (
        <p role="status" className="text-sm text-warning">
          {storageError}
        </p>
      )}
      <fieldset disabled={busy} className="space-y-4">
        <Card className="space-y-4">
          <CardTitle>1. Choose tickets</CardTitle>
          <p className="text-sm text-muted-foreground">
            Include only the tickets you want to use for this export. Unselected tickets are
            ignored.
          </p>
          {!Object.keys(tickets).length && <p>No WooCommerce tickets were found for this event.</p>}
          {settings.selectedTickets.some((id) => !tickets[id]) && (
            <Button
              variant="outline"
              onClick={() =>
                change({
                  ...settings,
                  selectedTickets: settings.selectedTickets.filter((id) => tickets[id]),
                })
              }
            >
              Remove selected tickets that are no longer available
            </Button>
          )}
          <div className="grid gap-3 sm:grid-cols-2">
            {Object.entries(tickets).map(([id, ticket]) => (
              <label
                key={id}
                className="flex items-center gap-3 rounded-lg border bg-background/40 p-3 text-sm"
              >
                <Checkbox
                  checked={settings.selectedTickets.includes(id)}
                  onCheckedChange={(checked) => selectTicket(id, checked === true)}
                />
                {ticket.name}
              </label>
            ))}
          </div>
          <div className="space-y-4 border-t pt-4">
            <label className="flex items-center gap-2 text-sm font-medium">
              <Checkbox
                checked={settings.seasonPass !== null}
                onCheckedChange={(checked) =>
                  change({
                    ...settings,
                    seasonPass: checked ? { eventId: '', ticketId: '', onlyHolders: false } : null,
                  })
                }
              />
              Include season pass holders
            </label>
            {settings.seasonPass && (
              <div className="space-y-4 rounded-lg border bg-background/40 p-4">
                <p className="text-sm text-muted-foreground">
                  Choose the event and ticket that sold season passes for this year. Map their
                  divisions below.
                </p>
                <div className="grid gap-4 sm:grid-cols-2">
                  <label className="space-y-2 text-sm">
                    <span className="block">Season pass event</span>
                    <select
                      className="h-9 w-full rounded-md border bg-background px-3 text-sm"
                      value={settings.seasonPass.eventId}
                      onChange={(event) =>
                        change({
                          ...settings,
                          seasonPass: {
                            ...settings.seasonPass!,
                            eventId: event.target.value,
                            ticketId: '',
                          },
                        })
                      }
                    >
                      <option value="">Choose an event</option>
                      {events.map((event) => (
                        <option key={event.id} value={event.id}>
                          {event.date} · {event.name}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label className="space-y-2 text-sm">
                    <span className="block">Season pass ticket</span>
                    <select
                      className="h-9 w-full rounded-md border bg-background px-3 text-sm"
                      value={settings.seasonPass.ticketId}
                      disabled={!passEventId || passLoading || Boolean(passError)}
                      onChange={(event) =>
                        change({
                          ...settings,
                          seasonPass: { ...settings.seasonPass!, ticketId: event.target.value },
                        })
                      }
                    >
                      <option value="">
                        {passLoading ? 'Loading tickets…' : 'Choose a ticket'}
                      </option>
                      {Object.entries(passTickets).map(([id, ticket]) => (
                        <option key={id} value={id}>
                          {ticket.name}
                        </option>
                      ))}
                    </select>
                  </label>
                </div>
                {passError && (
                  <p role="alert" className="text-negative">
                    {passError}
                  </p>
                )}
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={settings.seasonPass.onlyHolders}
                    onCheckedChange={(checked) =>
                      change({
                        ...settings,
                        seasonPass: { ...settings.seasonPass!, onlyHolders: checked === true },
                      })
                    }
                  />
                  Season pass holders only (CPR)
                </label>
                <p className="text-xs text-muted-foreground">
                  For CPR, selected event tickets must match a season pass holder. Unmatched
                  purchases are flagged for review.
                </p>
              </div>
            )}
          </div>
        </Card>
        <Card className="space-y-3">
          <CardTitle>2. Rodeo divisions</CardTitle>
          <p className="text-sm text-muted-foreground">
            Add a column for each division you want to import. Rodeo lets you map these columns to
            its divisions.
          </p>
          <div className="flex flex-wrap gap-2">
            {settings.divisions.map((name) => (
              <span
                key={name}
                className="flex items-center gap-2 rounded-md border border-primary/30 bg-primary/10 px-3 py-1 text-primary"
              >
                {name}
                <button
                  type="button"
                  aria-label={`Remove ${name}`}
                  onClick={() => removeDivision(name)}
                >
                  <X className="size-3.5" />
                </button>
              </span>
            ))}
          </div>
          <div className="flex max-w-md gap-2">
            <Input
              aria-label="New division name"
              placeholder="e.g. Main, Senior, Ladies"
              value={division}
              onChange={(e) => setDivision(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault();
                  addDivision();
                }
              }}
            />
            <Button variant="secondary" onClick={addDivision}>
              <Plus />
              Add division
            </Button>
          </div>
        </Card>
        <Card className="space-y-4">
          <CardTitle>3. Map selected tickets and checkboxes</CardTitle>
          <p className="text-sm text-muted-foreground">
            Map each selected ticket and registration checkbox to any of your divisions. Mark main
            entry tickets to check that every angler has a qualifying entry or season pass.
          </p>
          {!Object.keys(mappingTickets).length && <p>Select tickets above to begin mapping.</p>}
          {Object.entries(mappingTickets).map(([id, ticket]) => {
            const rule = settings.rules[id] || blankRule();
            const staleFields = Object.keys(rule.checkboxes).filter(
              (key) => !ticket.checkboxes[key],
            );

            return (
              <section key={id} className="space-y-3 rounded-lg border bg-background/40 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <h3 className="font-medium">{ticket.name}</h3>
                  {id === passTicketId ? (
                    <span className="text-xs text-primary">Season pass · qualifies as entry</span>
                  ) : (
                    <label className="flex items-center gap-2 text-xs text-muted-foreground">
                      <Checkbox
                        checked={rule.entry}
                        onCheckedChange={(checked) => updateRule(id, { entry: checked === true })}
                      />
                      Main entry ticket
                    </label>
                  )}
                </div>
                <DivisionChoices
                  label={ticket.name}
                  divisions={settings.divisions}
                  selected={rule.divisions}
                  onChange={(values) => updateRule(id, { divisions: values })}
                />
                {!settings.divisions.length && (
                  <p className="text-xs text-muted-foreground">
                    Add divisions above to begin mapping.
                  </p>
                )}
                {Object.entries(ticket.checkboxes).map(([key, field]) => (
                  <div key={key} className="space-y-2 border-l-2 border-primary/30 pl-4">
                    <p className="text-xs text-muted-foreground">
                      When checked: <span className="text-foreground">{field.label}</span>
                    </p>
                    <DivisionChoices
                      label={`${ticket.name}: ${field.label}`}
                      divisions={settings.divisions}
                      selected={rule.checkboxes[key] || []}
                      onChange={(values) =>
                        updateRule(id, { checkboxes: { ...rule.checkboxes, [key]: values } })
                      }
                    />
                  </div>
                ))}
                {staleFields.length > 0 && (
                  <Button
                    variant="outline"
                    onClick={() =>
                      updateRule(id, {
                        checkboxes: Object.fromEntries(
                          Object.entries(rule.checkboxes).filter(([key]) => ticket.checkboxes[key]),
                        ),
                      })
                    }
                  >
                    Remove mappings for deleted checkbox options
                  </Button>
                )}
              </section>
            );
          })}
        </Card>
        {!hasEntry && (
          <p className="text-sm text-warning">
            Mark an entry ticket to enable the “missing main entry” check.
          </p>
        )}
        <Button
          onClick={generate}
          disabled={
            !settings.divisions.length ||
            !Object.keys(mappingTickets).length ||
            busy ||
            passLoading ||
            Boolean(
              settings.seasonPass && (!passTicketId || !passTickets[passTicketId] || passError),
            )
          }
        >
          {busy ? <RefreshCw className="animate-spin" /> : preview ? <RefreshCw /> : <ArrowRight />}
          {busy ? 'Building preview…' : preview ? 'Refresh preview' : 'Preview export'}
        </Button>
      </fieldset>
      {error && (
        <div
          role="alert"
          className="rounded-lg border border-negative/40 bg-negative/10 p-3 text-negative"
        >
          {error}
        </div>
      )}
      {preview && (
        <Card className="space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <CardTitle>4. Review & export</CardTitle>
              <p className="mt-1 text-xs text-muted-foreground">
                Download uses this preview; refresh it to include new purchases. Attendee data and
                CSVs are not stored in the browser.
              </p>
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <MetricCard title="Unique anglers" value={preview.report.anglers.length} />
            <MetricCard title="Anglers to review" value={warningCount} />
            <MetricCard
              title="Data issues"
              value={preview.report.errors}
              tone={preview.report.errors ? 'negative' : undefined}
            />
            <MetricCard
              title="Excluded ticket records"
              value={Object.values(preview.report.excluded).reduce((a, b) => a + b, 0)}
            />
          </div>
          <div className="flex flex-wrap gap-3 text-sm">
            {Object.entries(preview.report.counts).map(([name, count]) => (
              <span key={name} className="rounded-md bg-secondary px-3 py-1">
                {name} <strong className="ml-2 text-primary">{count}</strong>
              </span>
            ))}
          </div>
          <p className="text-xs text-muted-foreground">
            Names split at the first space, preserving capitalization. Matching name and email
            combine tickets; different names or emails remain separate. Correct source attendee
            details and refresh when needed.
          </p>
          <label className="flex items-center gap-2 text-sm">
            <Checkbox checked={issuesOnly} onCheckedChange={(v) => setIssuesOnly(v === true)} />
            Show only anglers with issues
          </label>
          <Table caption="Grouped anglers for Rodeo">
            <thead>
              <tr className="border-b text-muted-foreground">
                <th>Angler</th>
                <th>Divisions</th>
                <th>Review</th>
              </tr>
            </thead>
            <tbody>
              {visible.map((a, i) => (
                <tr key={i} className="border-b align-top">
                  <td>
                    <p className="font-medium">
                      {a.first} {a.last}
                    </p>
                    <p className="text-xs text-muted-foreground">{a.email}</p>
                    <details className="mt-2 text-xs text-muted-foreground">
                      <summary className="cursor-pointer">
                        {a.sources.length} ticket record(s) · details
                      </summary>
                      <p>
                        First: {a.first} · Last: {a.last}
                      </p>
                      <p>
                        Phone: {a.phone || '—'} · Emergency: {a.emergency || '—'}
                      </p>
                      {a.sources.map((source) => (
                        <p key={source}>{source}</p>
                      ))}
                    </details>
                  </td>
                  <td>{a.divisions.join(', ') || '—'}</td>
                  <td>
                    {a.errors.map((s) => (
                      <p key={s} className="text-negative">
                        {s}
                      </p>
                    ))}
                    {a.warnings.map((s) => (
                      <p key={s} className="text-warning">
                        {s}
                      </p>
                    ))}
                    {!a.errors.length && !a.warnings.length && (
                      <span className="text-muted-foreground">Ready</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </Table>
          {!visible.length && (
            <p className="text-muted-foreground">
              {issuesOnly ? 'No issues to display.' : 'No anglers match these rules.'}
            </p>
          )}
          {Object.keys(preview.report.excluded).length > 0 && (
            <details className="text-sm text-muted-foreground">
              <summary className="cursor-pointer">Why records were excluded</summary>
              {Object.entries(preview.report.excluded).map(([reason, count]) => (
                <p key={reason} className="mt-1">
                  {reason}: {count}
                </p>
              ))}
            </details>
          )}
          <Button onClick={download} disabled={!preview.csv}>
            <Download />
            Download Rodeo CSV
          </Button>
          {(preview.report.errors > 0 || warningCount > 0) && (
            <p className="text-sm text-negative">
              All listed anglers are included in the CSV, including those with issues. You can
              correct the spreadsheet before importing into Rodeo.
            </p>
          )}
        </Card>
      )}
    </div>
  );
}
