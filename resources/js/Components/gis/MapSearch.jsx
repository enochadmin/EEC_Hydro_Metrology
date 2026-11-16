import { cn } from '@/Utils/cn';
import { GitBranch, Loader2, MapPin, RadioTower, Search, Waves, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const TYPE_ICON = {
    basin: Waves,
    sub_basin: GitBranch,
    station: RadioTower,
    feature: MapPin,
};

const TYPE_LABEL = {
    basin: 'Basin',
    sub_basin: 'Sub-basin',
    station: 'Station',
    feature: 'Map feature',
};

async function searchMap(term, signal) {
    const response = await fetch(`/gis/data/search?q=${encodeURIComponent(term)}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal,
    });

    if (!response.ok) {
        throw new Error('Search failed');
    }

    const payload = await response.json();

    return payload.results ?? [];
}

export default function MapSearch({ onSelect, className }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const containerRef = useRef(null);

    useEffect(() => {
        const term = query.trim();

        if (term.length < 2) {
            setResults([]);
            setLoading(false);

            return;
        }

        const controller = new AbortController();
        setLoading(true);

        const timer = window.setTimeout(async () => {
            try {
                const found = await searchMap(term, controller.signal);
                setResults(found);
                setOpen(true);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    setResults([]);
                }
            } finally {
                setLoading(false);
            }
        }, 300);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [query]);

    useEffect(() => {
        const handler = (event) => {
            if (containerRef.current && !containerRef.current.contains(event.target)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', handler);

        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const choose = (result) => {
        onSelect?.(result);
        setQuery('');
        setResults([]);
        setOpen(false);
    };

    return (
        <div ref={containerRef} className={cn('relative', className)}>
            <div className="flex items-center gap-2 rounded-lg border border-line bg-surface/95 px-3 shadow-lg backdrop-blur">
                <Search className="h-4 w-4 shrink-0 text-muted" strokeWidth={1.75} />
                <input
                    type="search"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    onFocus={() => results.length > 0 && setOpen(true)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter' && results[0]) {
                            choose(results[0]);
                        }

                        if (event.key === 'Escape') {
                            setOpen(false);
                        }
                    }}
                    placeholder="Search basins, sub-basins, stations…"
                    aria-label="Search the map"
                    className="h-10 w-full border-0 bg-transparent p-0 text-[12.5px] text-ink placeholder:text-muted/80 focus:ring-0"
                />
                {loading && <Loader2 className="h-3.5 w-3.5 animate-spin text-muted" strokeWidth={2} />}
                {!loading && query !== '' && (
                    <button type="button" onClick={() => setQuery('')} className="text-muted hover:text-ink" aria-label="Clear search">
                        <X className="h-3.5 w-3.5" strokeWidth={2} />
                    </button>
                )}
            </div>

            {open && query.trim().length >= 2 && (
                <div className="absolute left-0 right-0 top-[calc(100%+6px)] max-h-80 overflow-y-auto rounded-lg border border-line bg-surface p-1.5 shadow-xl">
                    {results.length === 0 && !loading && (
                        <p className="px-2.5 py-3 text-[11.5px] text-muted">No matching basin, sub-basin or station.</p>
                    )}

                    {results.map((result) => {
                        const Icon = TYPE_ICON[result.type] ?? MapPin;

                        return (
                            <button
                                key={`${result.type}-${result.id}-${result.layer ?? ''}`}
                                type="button"
                                onClick={() => choose(result)}
                                className="flex w-full items-start gap-2.5 rounded-md px-2.5 py-2 text-left transition-colors hover:bg-canvas"
                            >
                                <span className="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-md bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                    <Icon className="h-3.5 w-3.5" strokeWidth={1.75} />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-[12.5px] font-medium text-ink">{result.label}</span>
                                    <span className="block truncate text-[10.5px] text-muted">
                                        {TYPE_LABEL[result.type] ?? result.type}
                                        {result.sublabel ? ` · ${result.sublabel}` : ''}
                                    </span>
                                </span>
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
