<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Creates one restore point: a compressed logical dump of the database plus an
 * archive of the uploaded and generated files.
 *
 * The database password is passed through the child process environment rather
 * than the command line, so it never appears in a process listing or a log.
 */
class RunBackup extends Command
{
    protected $signature = 'eec:backup
        {--keep= : Delete backups older than this many days instead of the configured retention}
        {--prune-only : Delete expired backups without creating a new one}';

    protected $description = 'Back up the database and stored files, then prune expired backups';

    public function handle(): int
    {
        $directory = storage_path(config('backup.path'));

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $keep = (int) ($this->option('keep') ?: config('backup.retention_days'));

        if (! $this->option('prune-only')) {
            if (! $this->dumpDatabase($directory)) {
                return self::FAILURE;
            }

            $this->archiveFiles($directory);
        }

        $removed = $this->prune($directory, $keep);

        $this->components->info("Backups kept for {$keep} days; {$removed} expired file(s) removed.");

        return self::SUCCESS;
    }

    /**
     * Resolve the dump binary. A configured absolute path wins; otherwise the
     * usual XAMPP/Laragon locations are tried before giving up.
     */
    private function dumpBinary(): ?string
    {
        $configured = (string) config('backup.mysqldump');

        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $candidates = PHP_OS_FAMILY === 'Windows'
            ? [
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\laragon\\bin\\mysql\\mysql-8.0\\bin\\mysqldump.exe',
                'C:\\laragon\\bin\\mysql\\mysql-5.7\\bin\\mysqldump.exe',
            ]
            : ['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/opt/homebrew/bin/mysqldump'];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        // Fall back to the PATH, if the binary is there.
        $probe = new Process([$configured === '' ? 'mysqldump' : $configured, '--version']);
        $probe->run();

        return $probe->isSuccessful() ? ($configured === '' ? 'mysqldump' : $configured) : null;
    }

    private function dumpDatabase(string $directory): bool    {
        $connection = config('database.connections.'.config('database.default'));
        $stamp = Carbon::now()->format('Ymd-His');
        $target = $directory.DIRECTORY_SEPARATOR."eec-bhdms-db-{$stamp}.sql.gz";

        $binary = $this->dumpBinary();

        if ($binary === null) {
            $this->components->error(
                'mysqldump was not found. Set DB_DUMP_BINARY in .env to the full path of mysqldump (XAMPP: C:\\xampp\\mysql\\bin\\mysqldump.exe).'
            );

            return false;
        }

        $command = [
            $binary,
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.($connection['port'] ?? '3306'),
            '--user='.($connection['username'] ?? 'root'),
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--routines',
            '--default-character-set=utf8mb4',
            $connection['database'],
        ];

        $process = new Process($command, base_path(), [
            'MYSQL_PWD' => (string) ($connection['password'] ?? ''),
        ]);

        $process->setTimeout(1800);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->components->error('mysqldump failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));

            return false;
        }

        $handle = gzopen($target, 'wb9');

        if ($handle === false) {
            $this->components->error('Unable to open the backup file for writing.');

            return false;
        }

        gzwrite($handle, $process->getOutput());
        gzclose($handle);

        $this->components->info('Database dump: '.basename($target).' ('.number_format(filesize($target) / 1024, 1).' KB)');

        return true;
    }

    private function archiveFiles(string $directory): void
    {
        $stamp = Carbon::now()->format('Ymd-His');
        $target = $directory.DIRECTORY_SEPARATOR."eec-bhdms-files-{$stamp}.zip";

        $zip = new ZipArchive;

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->components->warn('Unable to create the file archive; the database dump was still written.');

            return;
        }

        $count = 0;

        foreach (config('backup.paths', []) as $relative) {
            $path = storage_path($relative);

            if (! File::isDirectory($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                $zip->addFile($file->getPathname(), $relative.'/'.$file->getRelativePathname());
                $count++;
            }
        }

        $zip->close();

        File::chmod($target, 0640);

        $this->components->info("File archive: ".basename($target)." ({$count} file(s))");
    }

    private function prune(string $directory, int $keep): int
    {
        $cutoff = Carbon::now()->subDays($keep)->getTimestamp();
        $removed = 0;

        foreach (File::files($directory) as $file) {
            if ($file->getMTime() >= $cutoff) {
                continue;
            }

            File::delete($file->getPathname());
            $removed++;
        }

        return $removed;
    }
}
