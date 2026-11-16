<?php

namespace App\Support\Gis;

use Illuminate\Support\Facades\Cache;

/**
 * Resolves the basemap styles the map is allowed to draw.
 *
 * A style that needs a key is offered only when the key is configured and the
 * stored probe result does not say it was rejected. Providers answer an
 * unauthenticated tile with an "API key required" placeholder that is itself a
 * valid PNG, so without this filter the map would draw that notice and look
 * like a broken map rather than a map missing a key.
 */
class BasemapRegistry
{
    /**
     * Cache entry written by `php artisan gis:basemap-key`.
     */
    public const PROBE_CACHE_KEY = 'gis.basemap-key.carto';

    /**
     * Styles the map may render right now.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->resolve()[0];
    }

    /**
     * The style the map should open with.
     *
     * @return array<string, mixed>|null
     */
    public function default(): ?array
    {
        $basemaps = $this->all();

        if ($basemaps === []) {
            return null;
        }

        $preferred = config('gis.default_basemap');

        foreach ($basemaps as $basemap) {
            if ($basemap['id'] === $preferred) {
                return $basemap;
            }
        }

        return $basemaps[0];
    }

    public function defaultId(): ?string
    {
        return $this->default()['id'] ?? null;
    }

    /**
     * Anything the UI should say about the styles it cannot offer.
     *
     * @return array{state: string, hidden: list<array{id: string, label: string, reason: string}>, message: string|null, checked_at: string|null}
     */
    public function status(): array
    {
        [, $hidden, $probe] = $this->resolve();

        if ($hidden === []) {
            return [
                'state' => 'ok',
                'hidden' => [],
                'message' => null,
                'checked_at' => is_array($probe) ? ($probe['checked_at'] ?? null) : null,
            ];
        }

        $missingKey = $this->key('carto') === null;

        return [
            'state' => $missingKey ? 'missing' : 'invalid',
            'hidden' => $hidden,
            'message' => $missingKey
                ? 'Light, Dark and Voyager are hidden until CARTO_BASEMAPS_KEY is set.'
                : 'CARTO rejected the configured key, so its styles are hidden. Run "php artisan gis:basemap-key" for details.',
            'checked_at' => is_array($probe) ? ($probe['checked_at'] ?? null) : null,
        ];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<array{id: string, label: string, reason: string}>, 2: array<string, mixed>|null}
     */
    private function resolve(): array
    {
        $probe = Cache::get(self::PROBE_CACHE_KEY);
        $probe = is_array($probe) ? $probe : null;

        $basemaps = [];
        $hidden = [];

        foreach (config('gis.basemaps', []) as $basemap) {
            $provider = $basemap['requires_key'] ?? null;

            if ($provider === null) {
                $basemaps[] = $this->present($basemap);
                continue;
            }

            $key = $this->key($provider);

            if ($key === null) {
                $hidden[] = $this->hidden($basemap, 'No basemaps key is configured.');
                continue;
            }

            if (($probe['state'] ?? null) === 'invalid') {
                $hidden[] = $this->hidden($basemap, 'The configured key was rejected by the provider.');
                continue;
            }

            $basemaps[] = $this->present($basemap, $key);
        }

        return [$basemaps, $hidden, $probe];
    }

    private function key(string $provider): ?string
    {
        $key = config('gis.basemap_keys.'.$provider);

        if (! is_string($key)) {
            return null;
        }

        $key = trim($key);

        return $key === '' ? null : $key;
    }

    /**
     * @param  array<string, mixed>  $basemap
     * @return array<string, mixed>
     */
    private function present(array $basemap, ?string $key = null): array
    {
        $url = $basemap['url'];

        if ($key !== null) {
            $url .= (str_contains($url, '?') ? '&' : '?').'key='.rawurlencode($key);
        }

        return array_filter([
            'id' => $basemap['id'],
            'label' => $basemap['label'],
            'url' => $url,
            'attribution' => $basemap['attribution'],
            'subdomains' => $basemap['subdomains'] ?? null,
            'max_zoom' => $basemap['max_zoom'] ?? 19,
            'requires_key' => isset($basemap['requires_key']) ? true : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $basemap
     * @return array{id: string, label: string, reason: string}
     */
    private function hidden(array $basemap, string $reason): array
    {
        return [
            'id' => $basemap['id'],
            'label' => $basemap['label'],
            'reason' => $reason,
        ];
    }
}
