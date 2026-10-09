import * as React from 'react';
import { cn } from '@/lib/utils';

export function Progress({
    value,
    className,
    ...props
}: React.ComponentProps<'div'> & { value?: number | null }) {
    const clampedValue = Math.min(Math.max(value ?? 0, 0), 100);

    return (
        <div
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={clampedValue}
            className={cn('relative h-2.5 w-full overflow-hidden rounded-full bg-muted', className)}
            {...props}
        >
            <div
                className="h-full bg-primary transition-all duration-300 ease-out"
                style={{ width: `${clampedValue}%` }}
            />
        </div>
    );
}
