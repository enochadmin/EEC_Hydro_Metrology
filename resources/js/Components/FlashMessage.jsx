import { cn } from '@/Utils/cn';
import { CheckCircle2, TriangleAlert, X } from 'lucide-react';
import { useEffect, useState } from 'react';

const tones = {
    success: {
        wrapper: 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100',
        icon: CheckCircle2,
    },
    error: {
        wrapper: 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100',
        icon: TriangleAlert,
    },
    warning: {
        wrapper: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100',
        icon: TriangleAlert,
    },
};

export default function FlashMessage({ flash }) {
    const tone = flash?.success ? 'success' : flash?.error ? 'error' : flash?.warning ? 'warning' : null;
    const text = tone ? flash[tone] : null;
    const [dismissed, setDismissed] = useState(false);

    useEffect(() => {
        setDismissed(false);
    }, [text]);

    if (!tone || !text || dismissed) {
        return null;
    }

    const { wrapper, icon: Icon } = tones[tone];

    return (
        <div className={cn('mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-[13px]', wrapper)} role="status">
            <Icon className="mt-0.5 h-4 w-4 shrink-0" strokeWidth={1.75} />
            <p className="flex-1 leading-relaxed">{text}</p>
            <button
                type="button"
                onClick={() => setDismissed(true)}
                aria-label="Dismiss message"
                className="rounded p-0.5 opacity-70 transition hover:opacity-100 focus-ring"
            >
                <X className="h-3.5 w-3.5" strokeWidth={2.25} />
            </button>
        </div>
    );
}
