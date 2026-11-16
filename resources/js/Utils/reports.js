/**
 * Formatting and palette shared by the reporting screens and their charts.
 *
 * A missing measurement stays "—": a gap in the record is not a zero reading,
 * and the charts must not draw it as one.
 */

export const CHART_COLORS = {
    line: '#0f766e',
    fill: '#14b8a6',
    compare: '#0ea5e9',
    distribution: '#6366f1',
    minimum: '#94a3b8',
    maximum: '#d97706',
    grid: '#e2e8f0',
};

export const GRANULARITY_LABELS = {
    daily: 'Daily',
    monthly: 'Monthly',
    annual: 'Annual',
};

export const STATUS_TONES = {
    verified: 'success',
    provisional: 'warning',
    approved: 'success',
    pending: 'warning',
    flagged: 'warning',
    rejected: 'danger',
    open: 'warning',
    resolved: 'success',
    ignored: 'neutral',
};

export function formatMetric(value, { unit = null, decimals = 2, fallback = '—' } = {}) {
    if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) {
        return fallback;
    }

    const formatted = Number(value).toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: decimals,
    });

    return unit ? `${formatted} ${unit}` : formatted;
}

export function formatCount(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    return Number(value).toLocaleString();
}

export function formatPercent(value) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '—';
    }

    return `${Number(value).toLocaleString(undefined, { maximumFractionDigits: 1 })}%`;
}

export function formatPeriod(value, granularity = 'monthly') {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    if (granularity === 'annual') {
        return String(date.getFullYear());
    }

    return date.toLocaleDateString(undefined, {
        month: 'short',
        year: 'numeric',
        ...(granularity === 'daily' ? { day: '2-digit' } : {}),
    });
}

export function coverageTone(percent) {
    if (percent === null || percent === undefined) {
        return 'neutral';
    }

    if (percent >= 95) {
        return 'success';
    }

    return percent >= 80 ? 'warning' : 'danger';
}

export function metricCaption(profile) {
    if (!profile) {
        return '';
    }

    const unit = profile.unit ? ` (${profile.unit})` : '';

    return `${profile.primary_label}${unit}`;
}
