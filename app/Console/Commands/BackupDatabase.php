<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * The daily full database backup, kept 30 days (specification section 12).
 * Backups are compressed files in storage/app/backups (on the preview site,
 * on its volume). SQLite is copied safely while in use; MySQL and PostgreSQL
 * use their own dump tools. Copying backups off the server is set up with
 * the live hosting.
 *
 *     php artisan wiifarm:backup
 */
class BackupDatabase extends Command
{
    protected $signature = 'wiifarm:backup {--keep=30 : Days to keep backups}';

    protected $description = 'Make a full database backup and delete those older than 30 days';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $driver = DB::connection()->getDriverName();
        $file = $dir.'/wiifarm-'.now()->format('Y-m-d-His').'.'.($driver === 'sqlite' ? 'sqlite' : 'sql');

        if ($driver === 'sqlite') {
            // VACUUM INTO writes a consistent copy even while the site is in use.
            DB::statement('VACUUM INTO ?', [$file]);
        } else {
            $c = config('database.connections.'.config('database.default'));
            $command = $driver === 'pgsql'
                ? ['pg_dump', '--no-owner', '-h', $c['host'], '-p', (string) $c['port'], '-U', $c['username'], '-f', $file, $c['database']]
                : ['mysqldump', '--single-transaction', '-h', $c['host'], '-P', (string) $c['port'], '-u', $c['username'], '--result-file='.$file, $c['database']];
            $process = new Process($command, null, ['PGPASSWORD' => $c['password'] ?? '', 'MYSQL_PWD' => $c['password'] ?? '']);
            $process->setTimeout(1800)->run();
            if (! $process->isSuccessful()) {
                $this->error('The backup failed: '.trim($process->getErrorOutput()));

                return self::FAILURE;
            }
        }

        // Compress it.
        $gz = $file.'.gz';
        $in = fopen($file, 'rb');
        $out = gzopen($gz, 'wb6');
        while (! feof($in)) {
            gzwrite($out, fread($in, 1 << 20));
        }
        fclose($in);
        gzclose($out);
        unlink($file);
        @chmod($gz, 0600);

        // Keep the last N days.
        $cutoff = now()->subDays((int) $this->option('keep'))->getTimestamp();
        $removed = 0;
        foreach (File::glob($dir.'/wiifarm-*.gz') as $old) {
            if (filemtime($old) < $cutoff && unlink($old)) {
                $removed++;
            }
        }

        $this->info('Backup made: '.basename($gz).' ('.round(filesize($gz) / 1024).' KB). Old backups removed: '.$removed.'.');

        return self::SUCCESS;
    }
}
