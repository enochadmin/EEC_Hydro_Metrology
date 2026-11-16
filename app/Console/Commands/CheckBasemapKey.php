<?php

namespace App\Console\Commands;

use App\Support\Gis\BasemapRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Confirms that a keyed basemap key is actually accepted by the provider.
 *
 * CARTO serves a placeholder tile with HTTP 200 when the key is missing or not
 * recognised, so a status code alone proves nothing. The probe requests the
 * same tile twice — with and without the key — and compares the bytes. An
 * identical response means the provider ignored the key.
 */
class CheckBasemapKey extends Command
{
    protected $signature = 'gis:basemap-key {--clear : Forget the stored probe result instead of probing}';

    protected $description = 'Probe the configured CARTO basemaps key and remember whether the provider accepts it';

    public function handle(): int
    {
        if ($this->option('clear')) {
            Cache::forget(BasemapRegistry::PROBE_CACHE_KEY);
            $this->components->info('Stored basemaps key result cleared.');

            return self::SUCCESS;
        }

        $key = config('gis.basemap_keys.carto');

        if (! is_string($key) || trim($key) === '') {
            $this->components->error('CARTO_BASEMAPS_KEY is empty. Request a key at https://carto.com/basemaps/apikey/ and add it to .env.');

            return self::FAILURE;
        }

        $probe = config('gis.basemap_key_probe');

        $this->line('Probing '.$probe);

        try {
            $anonymous = Http::timeout(25)->withHeaders(['Accept' => 'image/png'])->get($probe);
            $authenticated = Http::timeout(25)->withHeaders(['Accept' => 'image/png'])->get($probe.'?key='.rawurlencode(trim($key)));
        } catch (\Throwable $exception) {
            $this->components->error('Could not reach the basemap service: '.$exception->getMessage());

            return self::FAILURE;
        }

        $detail = $this->classify($anonymous->status(), $authenticated->status(), $anonymous->body(), $authenticated->body());

        $state = $detail['state'];

        Cache::put(
            BasemapRegistry::PROBE_CACHE_KEY,
            [
                'state' => $state,
                'checked_at' => now()->toIso8601String(),
                'detail' => $detail['message'],
            ],
            now()->addHours(24),
        );

        $this->newLine();
        $this->components->twoColumnDetail('Key present', 'yes');
        $this->components->twoColumnDetail('Anonymous tile', $anonymous->status().' · '.strlen($anonymous->body()).' bytes');
        $this->components->twoColumnDetail('Keyed tile', $authenticated->status().' · '.strlen($authenticated->body()).' bytes');
        $this->components->twoColumnDetail('Verdict', $state);

        if ($state === 'valid') {
            $this->components->info('The provider accepted the key. Light, Dark and Voyager can now be selected on the map.');
        } else {
            $this->components->error($detail['message']);
            $this->line('  Re-issue the key from https://dashboard.basemaps.carto.com/ (sign in by e-mail) and paste the new value into .env.');
        }

        return $state === 'valid' ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{state: string, message: string}
     */
    private function classify(int $anonymousStatus, int $authenticatedStatus, string $anonymousBody, string $authenticatedBody): array
    {
        if ($authenticatedStatus === 403) {
            return ['state' => 'invalid', 'message' => 'The provider refused the key (403). If the key has website restrictions, add this host in the CARTO dashboard.'];
        }

        if ($authenticatedStatus >= 400) {
            return ['state' => 'invalid', 'message' => 'The provider answered the keyed request with HTTP '.$authenticatedStatus.'.'];
        }

        if ($authenticatedBody === '' || ! str_starts_with($authenticatedBody, "\x89PNG")) {
            return ['state' => 'invalid', 'message' => 'The keyed request did not return a PNG tile, so the key was not accepted.'];
        }

        if ($authenticatedBody === $anonymousBody) {
            return ['state' => 'invalid', 'message' => 'The keyed tile is byte-identical to the anonymous tile, so the key was ignored. This token is probably a CARTO platform API token rather than a Basemaps key.'];
        }

        return ['state' => 'valid', 'message' => 'The keyed tile differs from the anonymous tile, so the key is accepted.'];
    }
}
