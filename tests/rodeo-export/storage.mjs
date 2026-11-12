import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';

const source = readFileSync(
  new URL('../../src/features/rodeo-export/storage.ts', import.meta.url),
  'utf8',
);
const compiled = ts.transpileModule(source, {
  compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ESNext },
}).outputText;
const storage = await import(
  `data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`
);

test('event setup storage expires, resets, and tolerates unavailable storage', () => {
  const entries = new Map();
  const RealDate = Date;
  let now = RealDate.parse('2026-08-31T15:30:00Z');
  const originalStorage = Object.getOwnPropertyDescriptor(globalThis, 'localStorage');

  globalThis.Date = class extends RealDate {
    constructor(...args) {
      super(...(args.length ? args : [now]));
    }

    static now() {
      return now;
    }
  };

  Object.defineProperty(globalThis, 'localStorage', {
    configurable: true,
    value: {
      get length() {
        return entries.size;
      },
      key(index) {
        return [...entries.keys()][index] ?? null;
      },
      getItem(key) {
        return entries.get(key) ?? null;
      },
      setItem(key, value) {
        entries.set(key, value);
      },
      removeItem(key) {
        entries.delete(key);
      },
    },
  });

  try {
    const setup = {
      divisions: ['Main'],
      selectedTickets: ['10'],
      rules: { 10: { entry: true, divisions: ['Main'], checkboxes: {} } },
      seasonPass: { eventId: '2', ticketId: '20', onlyHolders: true },
    };
    assert.equal(storage.saveSetup('1', setup), true);
    const key = [...entries.keys()][0];
    const saved = entries.get(key);
    assert.equal(JSON.parse(saved).expiresAt, RealDate.parse('2027-02-28T15:30:00Z'));
    assert.deepEqual(Object.keys(JSON.parse(saved)).sort(), ['expiresAt', 'settings']);
    assert.deepEqual(storage.loadSetup('1'), setup);
    assert.deepEqual(storage.loadSetup('2'), storage.emptySettings());
    assert.equal(entries.get(key), saved, 'Loading does not extend expiration');

    now = RealDate.parse('2026-09-01T15:30:00Z');
    storage.saveSetup('2', setup);
    const secondKey = [...entries.keys()].find((value) => value !== key);
    entries.set('another-tool', 'keep');
    entries.set(key + '-broken', '{');
    now = RealDate.parse('2027-02-28T15:30:00Z');
    storage.clearExpiredSetups();
    assert.equal(entries.has(key), false, 'Expires at the exact deadline');
    assert.equal(entries.has(key + '-broken'), false);
    assert.equal(entries.get('another-tool'), 'keep');
    assert.equal(entries.has(secondKey), true, 'Other unexpired events survive cleanup');

    storage.saveSetup('2', setup);
    assert.equal(
      JSON.parse(entries.get(secondKey)).expiresAt,
      RealDate.parse('2027-08-28T15:30:00Z'),
    );
    assert.equal(storage.removeSetup('2'), true);
    assert.deepEqual(storage.loadSetup('2'), storage.emptySettings());

    Object.defineProperty(globalThis, 'localStorage', {
      configurable: true,
      get() {
        throw new Error('Storage disabled');
      },
    });
    assert.doesNotThrow(() => storage.clearExpiredSetups());
    assert.deepEqual(storage.loadSetup('1'), storage.emptySettings());
    assert.equal(storage.saveSetup('1', setup), false);
    assert.equal(storage.removeSetup('1'), false);
  } finally {
    globalThis.Date = RealDate;

    if (originalStorage) {
      Object.defineProperty(globalThis, 'localStorage', originalStorage);
    } else {
      delete globalThis.localStorage;
    }
  }
});
