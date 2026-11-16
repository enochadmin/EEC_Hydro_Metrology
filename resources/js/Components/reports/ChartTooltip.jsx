import { formatMetric } from '@/Utils/reports';

const SERIES_LABELS = {
    value: 'Value',
    min: 'Minimum',
    max: 'Maximum',
    records: 'Readings',
};

/**
 * Shared tooltip. Recharts injects `active`, `payload` and `label`, so every
 * chart shows the same units and precision.
 */
export default function ChartTooltip({ active, payload, label, unit = null, decimals = 2 }) {
    if (!active || !payload || payload.length === 0) {
        return null;
    }

    return (
        <div className="rounded-lg border border-line bg-surface px-3 py-2 text-[11px] shadow-card">
            <p className="font-medium text-ink">{label}</p>
            <ul className="mt-1 space-y-0.5">
                {payload.map((entry) => (
                    <li key={entry.dataKey} className="flex items-center gap-2">
                        <span className="h-1.5 w-1.5 rounded-full" style={{ background: entry.color ?? entry.fill }} />
                        <span className="text-muted">{SERIES_LABELS[entry.dataKey] ?? entry.dataKey}</span>
                        <span className="ml-auto font-medium tabular text-ink">
                            {entry.dataKey === 'records'
                                ? Number(entry.value).toLocaleString()
                                : formatMetric(entry.value, { unit, decimals })}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
