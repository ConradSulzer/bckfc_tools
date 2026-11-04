import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

const densityClasses = {
  compact: '[--table-x:0.5rem] [--table-y:0.5rem]',
  default: '[--table-x:0.75rem] [--table-y:0.5rem]',
  comfortable: '[--table-x:1rem] [--table-y:0.625rem]',
  worksheet: '[--table-x:0.75rem] [--table-y:0.375rem] [--table-head-y:0.5rem]',
};
/** Share table spacing without owning feature-specific columns, grouping or actions. */
export function Table({
  caption,
  density = 'default',
  className,
  containerClassName,
  children,
  ...props
}: ComponentProps<'table'> & {
  caption: string;
  density?: keyof typeof densityClasses;
  containerClassName?: string;
}) {
  return (
    <div className={cn('overflow-x-auto', containerClassName)}>
      <table
        className={cn(
          'tools-table w-full text-left text-sm [--table-head-y:var(--table-y)]',
          densityClasses[density],
          className,
        )}
        {...props}
      >
        <caption className="sr-only">{caption}</caption>
        {children}
      </table>
    </div>
  );
}
