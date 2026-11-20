<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The daily backup (specification section 12). It runs on a real database
 * file, outside a transaction, as on the server.
 */
class BackupTest extends TestCase
{
    public function test_the_backup_makes_a_compressed_copy_and_removes_old_ones()
    {
        $db = tempnam(sys_get_temp_dir(), 'wf').'.sqlite';
        touch($db);
        config(['database.connections.sqlite.database' => $db]);
        DB::purge('sqlite');
        DB::statement('CREATE TABLE members (name TEXT)');
        DB::table('members')->insert(['name' => 'ada']);

        $dir = storage_path('app/backups');
        File::deleteDirectory($dir);
        File::ensureDirectoryExists($dir);
        touch($old = $dir.'/wiifarm-2020-01-01-000000.sqlite.gz', now()->subDays(40)->getTimestamp());

        $this->artisan('wiifarm:backup')->expectsOutputToContain('Old backups removed: 1')->assertSuccessful();

        $files = File::glob($dir.'/wiifarm-*.gz');
        $this->assertCount(1, $files);
        $this->assertFileDoesNotExist($old);
        // The copy is a working database with the data in it.
        $copy = $db.'.restored';
        file_put_contents($copy, gzdecode(file_get_contents($files[0])));
        $this->assertSame('ada', (new \PDO('sqlite:'.$copy))->query('SELECT name FROM members')->fetchColumn());

        File::deleteDirectory($dir);
        @unlink($db);
        @unlink($copy);
    }
}
