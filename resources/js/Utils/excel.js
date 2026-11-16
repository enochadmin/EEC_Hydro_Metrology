export const STRUCTURE_LABELS = {
    monthly_daily_columns: 'Monthly records · daily columns',
    multi_header_matrix: 'Multi-header matrix',
    daily_series: 'Daily date/value series',
};

export function structureLabel(key) {
    return STRUCTURE_LABELS[key] ?? (key ? key.replaceAll('_', ' ') : 'Not detected');
}

export function stageLabel(stage) {
    return {
        uploaded: 'Uploaded',
        mapped: 'Mapped',
        previewed: 'Transformed',
        validated: 'Validated',
        compared: 'Compared',
        imported: 'Imported',
    }[stage] ?? stage ?? 'Uploaded';
}

export function stageTone(stage) {
    return {
        uploaded: 'neutral',
        mapped: 'brand',
        previewed: 'brand',
        validated: 'warning',
        compared: 'warning',
        imported: 'success',
    }[stage] ?? 'neutral';
}

export function batchTone(status) {
    return {
        completed: 'success',
        completed_with_errors: 'warning',
        failed: 'danger',
        processing: 'brand',
        pending: 'neutral',
    }[status] ?? 'neutral';
}

export function severityTone(severity) {
    return severity === 'error' ? 'danger' : 'warning';
}
