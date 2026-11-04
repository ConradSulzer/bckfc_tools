import { PocketKnife, ClipboardList } from 'lucide-react';
import { RodeoAnglerExport } from './features/rodeo-export/RodeoAnglerExport';

export function App() {
  const page = new URL(location.href).searchParams.get('page');
  const isRodeoExport = page === 'bckfc-rodeo-export' || page === 'bckfc-tools';

  return (
    <div className="flex min-w-0 flex-1 flex-col overflow-hidden rounded-xl border bg-background text-foreground shadow-sm">
      <header className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
        <div className="flex items-center gap-3">
          <div className="flex size-8 items-center justify-center rounded-xl bg-primary text-primary-foreground">
            <PocketKnife className="size-5" />
          </div>
          <div>
            <p className="text-base font-semibold tracking-wide">
              BCKFC{' '}
              <span className="ml-1 text-xs font-normal tracking-[0.18em] text-muted-foreground">
                TOOLS
              </span>
            </p>
          </div>
        </div>
        <span className="text-xs text-muted-foreground">Club administration</span>
      </header>
      {isRodeoExport && (
        <nav aria-label="Tools" className="flex border-y px-4">
          <span className="flex items-center gap-2 border-b-2 border-primary py-3 text-sm text-primary">
            <ClipboardList className="size-4" />
            Rodeo Angler Export
          </span>
        </nav>
      )}
      <main className="p-4">
        {isRodeoExport ? <RodeoAnglerExport /> : <p>This tool is not available.</p>}
      </main>
    </div>
  );
}
