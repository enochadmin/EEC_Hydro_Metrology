/**
 * Display helpers shared by the registry screens.
 *
 * A missing value is rendered as "—" rather than 0: the registry must never
 * imply that unverified geography is zero.
 */

export function formatNumber(value, { maximumFractionDigits = 0, fallback = '—' } = {}) {
    if (value === null || value === undefined || value === '') {
        return fallback;
    }

    const number = Number(value);

    if (Number.isNaN(number)) {
        return fallback;
    }

    return number.toLocaleString(undefined, { maximumFractionDigits });
}

export function formatArea(value) {
    if (value === null || value === undefined || value === '') {
        return 'Not recorded';
    }

    return `${formatNumber(value, { maximumFractionDigits: 2 })} km²`;
}

export function formatElevation(value) {
    if (value === null || value === undefined || value === '') {
        return 'Not recorded';
    }

    return `${formatNumber(value, { maximumFractionDigits: 0 })} m`;
}

export function formatCoordinate(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const number = Number(value);

    return Number.isNaN(number) ? '—' : number.toFixed(5);
}

export function formatCoordinates(lat, lng) {
    if (lat === null || lat === undefined || lng === null || lng === undefined || lat === '' || lng === '') {
        return 'Not recorded';
    }

    return `${formatCoordinate(lat)}, ${formatCoordinate(lng)}`;
}

export function formatDate(value, fallback = '—') {
    if (!value) {
        return fallback;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return fallback;
    }

    return date.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
}

export function formatDateTime(value, fallback = '—') {
    if (!value) {
        return fallback;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return fallback;
    }

    return date.toLocaleString(undefined, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatBytes(bytes) {
    const value = Number(bytes);

    if (!Number.isFinite(value) || value <= 0) {
        return '0 KB';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    const exponent = Math.min(Math.floor(Math.log(value) / Math.log(1024)), units.length - 1);
    const amount = value / 1024 ** exponent;

    return `${amount >= 10 || exponent === 0 ? Math.round(amount) : amount.toFixed(1)} ${units[exponent]}`;
}
