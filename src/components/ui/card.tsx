import type { ComponentProps, ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function Card({ className, ...props }: ComponentProps<'section'>) {
  return <section className={cn('min-w-0 rounded-xl border bg-card p-3', className)} {...props} />;
}

export function CardTitle({ className, ...props }: ComponentProps<'h2'>) {
  return <h2 className={cn('font-semibold', className)} {...props} />;
}

export function MetricCard({
  title,
  value,
  tone,
  className,
}: {
  title: string;
  value: ReactNode;
  tone?: 'positive' | 'negative';
  className?: string;
}) {
  return (
    <Card
      className={cn(
        tone === 'positive' && 'border-positive/40 bg-positive/5',
        tone === 'negative' && 'border-negative/40 bg-negative/5',
        className,
      )}
    >
      <CardTitle className="text-sm font-normal text-muted-foreground">{title}</CardTitle>
      <p
        className={cn(
          'mt-2 text-xl font-semibold tabular-nums',
          tone === 'positive' && 'text-positive',
          tone === 'negative' && 'text-negative',
        )}
      >
        {value}
      </p>
    </Card>
  );
}
