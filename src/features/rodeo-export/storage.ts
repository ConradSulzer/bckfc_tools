import type { Settings } from './types';

const prefix = 'bckfc-tools:rodeo-export:v1:';

export function emptySettings(): Settings {
  return { divisions: [], rules: {}, selectedTickets: [], seasonPass: null };
}

function isSettings(value: unknown): value is Settings {
  if (!value || typeof value !== 'object') return false;

  const settings = value as Settings;
  const strings = (items: unknown): items is string[] =>
    Array.isArray(items) && items.every((item) => typeof item === 'string');

  return (
    strings(settings.divisions) &&
    strings(settings.selectedTickets) &&
    Boolean(settings.rules) &&
    typeof settings.rules === 'object' &&
    !Array.isArray(settings.rules) &&
    Object.values(settings.rules).every(
      (rule) =>
        rule &&
        typeof rule.entry === 'boolean' &&
        strings(rule.divisions) &&
        rule.checkboxes &&
        typeof rule.checkboxes === 'object' &&
        !Array.isArray(rule.checkboxes) &&
        Object.values(rule.checkboxes).every(strings),
    ) &&
    (settings.seasonPass === null ||
      Boolean(
        settings.seasonPass &&
        typeof settings.seasonPass.eventId === 'string' &&
        typeof settings.seasonPass.ticketId === 'string' &&
        typeof settings.seasonPass.onlyHolders === 'boolean',
      ))
  );
}

function readSetup(key: string): Settings | null {
  const raw = localStorage.getItem(key);

  if (!raw) return null;

  try {
    const stored = JSON.parse(raw);

    if (
      Number.isFinite(stored?.expiresAt) &&
      stored.expiresAt > Date.now() &&
      isSettings(stored.settings)
    ) {
      return stored.settings;
    }
  } catch {
    // A damaged entry is discarded just like an expired one.
  }

  localStorage.removeItem(key);
  return null;
}

export function clearExpiredSetups(): void {
  try {
    const keys = Array.from({ length: localStorage.length }, (_, index) => localStorage.key(index));

    for (const key of keys) {
      if (key?.startsWith(prefix)) readSetup(key);
    }
  } catch {
    // Storage may be disabled; the exporter can still work in memory.
  }
}

export function loadSetup(eventId: string): Settings {
  try {
    return readSetup(prefix + eventId) ?? emptySettings();
  } catch {
    return emptySettings();
  }
}

export function saveSetup(eventId: string, settings: Settings): boolean {
  const expires = new Date();
  const day = expires.getUTCDate();
  expires.setUTCDate(1);
  expires.setUTCMonth(expires.getUTCMonth() + 6);
  const lastDay = new Date(
    Date.UTC(expires.getUTCFullYear(), expires.getUTCMonth() + 1, 0),
  ).getUTCDate();
  expires.setUTCDate(Math.min(day, lastDay));

  try {
    localStorage.setItem(
      prefix + eventId,
      JSON.stringify({ expiresAt: expires.getTime(), settings }),
    );
    return true;
  } catch {
    return false;
  }
}

export function removeSetup(eventId: string): boolean {
  try {
    localStorage.removeItem(prefix + eventId);
    return true;
  } catch {
    return false;
  }
}
