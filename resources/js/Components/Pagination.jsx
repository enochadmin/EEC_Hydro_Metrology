import Button from '@/Components/Button';

/**
 * Compact pagination for Laravel paginators.
 *
 * Accepts the serialized paginator object (current_page, last_page, total…).
 * Buttons render as Inertia links when a page URL exists.
 */
export default function Pagination({ meta, className }) {
    if (!meta || meta.total === 0) {
        return null;
    }

    const from = meta.from ?? 0;
    const to = meta.to ?? 0;

    return (
        <div className={`flex flex-col gap-3 border-t border-line px-5 py-3 sm:flex-row sm:items-center sm:justify-between ${className ?? ''}`}>
            <p className="text-xs text-muted">
                Showing <span className="font-medium text-ink tabular">{from}</span>–
                <span className="font-medium text-ink tabular">{to}</span> of{' '}
                <span className="font-medium text-ink tabular">{meta.total}</span>
            </p>

            <div className="flex items-center gap-2">
                <span className="mr-1 text-xs text-muted tabular">
                    Page {meta.current_page} of {meta.last_page}
                </span>

                {meta.prev_page_url ? (
                    <Button href={meta.prev_page_url} variant="secondary" size="sm" preserveScroll>
                        Previous
                    </Button>
                ) : (
                    <Button variant="secondary" size="sm" disabled>
                        Previous
                    </Button>
                )}

                {meta.next_page_url ? (
                    <Button href={meta.next_page_url} variant="secondary" size="sm" preserveScroll>
                        Next
                    </Button>
                ) : (
                    <Button variant="secondary" size="sm" disabled>
                        Next
                    </Button>
                )}
            </div>
        </div>
    );
}