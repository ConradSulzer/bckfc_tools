# BCKFC Tools

Standalone WordPress plugin for internal Bayou Coast Kayak Fishing Club tools.

## Development

Node 24 (`.tool-versions` / `.nvmrc`), PHP 8.2+, Event Tickets, Event Tickets Plus, WooCommerce.

```sh
npm ci
npm run build
npm run format
npm run format:check
php tests/export.php
```

`build/` is generated and ignored. Package `bckfc-tools.php`, `includes/`, `assets/`, and `build/` under a `bckfc-tools/` directory for installation. No Node runtime is needed on the server.

- `EventSource`: ticket/checkbox discovery, attendee and order reads.
- `ExportSettings`: division and mapping validation.
- `RodeoExport`: grouping, warnings and CSV output.
- `ExportController`: REST permissions and preview requests.
- `Admin`: menu and asset integration.
- `src/App.tsx`: shared Tools shell and selection of the tool for the WordPress admin page.
- `src/api/client.ts`: shared HTTP transport, authentication, JSON and errors; callers specify the method explicitly.
- `src/features/rodeo-export/`: Rodeo event selection, mapping/preview UI, types and named API operations.
- `src/components/ui/`: independent copies of Finances UI primitives.

Local installation is symlinked from `bayou_coast/app/public/wp-content/plugins/bckfc-tools`. The local plugin allowlist includes Tools; HTTP/mail blocks remain enabled. No production deployment or remote repository has been created.

Prettier formats PHP, TypeScript/React, CSS, JSON and Markdown. Run `npm run format` after edits; `npm run format:check` checks formatting without changing files. Generated builds and the dependency lockfile are excluded.

Keep a blank line between functions and between separate control-flow blocks and surrounding statements. Keep related `if`/`else` branches together. Prettier preserves this spacing but does not insert it automatically.

## Rodeo exports

Choose the year/event, select the tickets to include, then define your divisions and map the selected tickets and attendee checkboxes. Unselected tickets are ignored. Main entry flags validate eligibility independently of division names.

Enable **Include season pass holders** to choose a pass event in the same year and its ticket. Pass holders are included even without a purchase at the current event, qualify as entry, and use your division mappings. When the pass was sold at the current event, its attendee records are read once.

Enable **Season pass holders only (CPR)** to require a pass for every selected event purchaser. Unmatched purchasers are flagged for review. For other events, missing entry is flagged when an entry ticket or season pass is configured. Data issues never block CSV download, and flagged anglers remain included so corrections can be made in the spreadsheet.

Tickets combine by normalized attendee name and email, including purchases across events. Different identities remain separate for review; purchaser identity does not merge family members. Correct details in the downloaded spreadsheet, or update Event Tickets and refresh the preview. CSV division columns use explicit TRUE/FALSE values.

Phone and emergency-contact values export as ten digits. Formatting and an optional leading North American country code `1` are removed. Invalid lengths or area-code/exchange prefixes become blank; this checks format, not whether a number is assigned or reachable.

Export setup is saved per event in browser localStorage for six calendar months after the last edit (clamped to the last day of the expiry month). Opening the exporter removes expired entries without extending their expiration. Reset setup removes the current event’s entry. Attendee data, previews, and CSVs are never stored; nothing is written to WordPress. If browser storage is unavailable, setup stays in memory.
