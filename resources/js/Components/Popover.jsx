import { cn } from '@/Utils/cn';
import { useEffect, useId, useRef, useState } from 'react';

/**
 * Lightweight dropdown used by the topbar (notifications, theme, account).
 *
 * Kept dependency-free on purpose: it only needs outside-click and Escape
 * handling, and every trigger stays a real button for keyboard users.
 */
export default function Popover({ trigger, children, align = 'end', width = 'w-72', panelClassName, label }) {
    const [open, setOpen] = useState(false);
    const containerRef = useRef(null);
    const panelId = useId();

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const handlePointerDown = (event) => {
            if (containerRef.current && !containerRef.current.contains(event.target)) {
                setOpen(false);
            }
        };

        const handleKeyDown = (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', handlePointerDown);
        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('mousedown', handlePointerDown);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [open]);

    return (
        <div className="relative" ref={containerRef}>
            {trigger({
                open,
                toggle: () => setOpen((value) => !value),
                close: () => setOpen(false),
                panelId,
                label,
            })}

            {open && (
                <div
                    id={panelId}
                    className={cn(
                        'absolute z-50 mt-2 origin-top-right animate-pop-in overflow-hidden rounded-xl border border-line bg-surface shadow-lift',
                        align === 'end' ? 'right-0' : 'left-0',
                        width,
                        panelClassName,
                    )}
                >
                    {typeof children === 'function' ? children({ close: () => setOpen(false) }) : children}
                </div>
            )}
        </div>
    );
}
