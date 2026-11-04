export type Rule = { entry: boolean; divisions: string[]; checkboxes: Record<string, string[]> };
export type SeasonPass = { eventId: string; ticketId: string; onlyHolders: boolean };
export type Settings = {
  divisions: string[];
  rules: Record<string, Rule>;
  selectedTickets: string[];
  seasonPass: SeasonPass | null;
};
export type Ticket = { name: string; checkboxes: Record<string, { label: string }> };
export type Event = { id: number; name: string; date: string };
export type Angler = {
  first: string;
  last: string;
  email: string;
  phone: string;
  emergency: string;
  divisions: string[];
  sources: string[];
  warnings: string[];
  errors: string[];
};
export type Preview = {
  csv: string;
  report: {
    anglers: Angler[];
    counts: Record<string, number>;
    excluded: Record<string, number>;
    errors: number;
  };
};

export type EventTickets = { tickets: Record<string, Ticket> };

export type EventYears = { currentYear: number; years: number[] };
