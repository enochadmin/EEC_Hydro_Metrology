/**
 * Shared vocabulary for the basin registry.
 *
 * Keeping the labels and tones in one place stops the index, detail tabs and
 * sub-basin screens from drifting apart.
 */

export function dataStatusTone(status) {
    switch (status) {
        case 'verified':
            return 'success';
        case 'provisional':
            return 'warning';
        default:
            return 'neutral';
    }
}

export function dataStatusLabel(status) {
    switch (status) {
        case 'verified':
            return 'Verified';
        case 'provisional':
            return 'Provisional';
        default:
            return 'Pending';
    }
}

export function boundaryStatusMeta(status) {
    switch (status) {
        case 'mapped':
            return {
                label: 'Boundary mapped',
                short: 'Mapped',
                tone: 'success',
                description: 'A primary polygon is stored in WGS84 (SRID 4326).',
            };
        case 'unverified':
            return {
                label: 'Boundary unverified',
                short: 'Unverified',
                tone: 'warning',
                description: 'Polygon versions exist but none is marked as the primary boundary.',
            };
        default:
            return {
                label: 'Boundary pending',
                short: 'Pending',
                tone: 'neutral',
                description: 'No polygon has been imported yet.',
            };
    }
}

export function stationTypeLabel(type) {
    return String(type ?? '')
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

export function activityLabel(event) {
    const labels = {
        'basin.created': 'Basin created',
        'basin.updated': 'Basin updated',
        'basin.deleted': 'Basin deleted',
        'basin.boundary.imported': 'Boundary imported',
        'basin.boundary.deleted': 'Boundary removed',
        'sub-basin.created': 'Sub-basin created',
        'sub-basin.updated': 'Sub-basin updated',
        'sub-basin.deleted': 'Sub-basin deleted',
        'sub-basin.boundary.imported': 'Boundary imported',
        'sub-basin.boundary.deleted': 'Boundary removed',
        'document.uploaded': 'Document uploaded',
        'document.deleted': 'Document removed',
    };

    return labels[event] ?? event;
}
