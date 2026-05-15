<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BackupApplication extends Command
{
    protected $signature = 'app:backup {--name= : Custom backup folder name} {--without-env : Do not include the .env file in the backup}';

    protected $description = 'Create a local backup of database and uploaded public storage files.';

    public function handle(): int
    {
        $name = $this->option('name') ?: now()->format('Ymd-His');
        $target = storage_path("app/backups/{$name}");

        File::ensureDirectoryExists($target);

        if (! $this->option('without-env')) {
            $this->backupEnvironment($target);
        }
        $this->backupDatabase($target);
        $this->backupPublicStorage($target);

        $this->info("Backup created: {$target}");

        return self::SUCCESS;
    }

    private function backupEnvironment(string $target): void
    {
        $envPath = base_path('.env');

        if (File::exists($envPath)) {
            File::copy($envPath, "{$target}/env.backup");
        }
    }

    private function backupDatabase(string $target): void
    {
        if (config('database.default') !== 'sqlite') {
            File::put("{$target}/DATABASE_BACKUP_NOTE.txt", 'Non-SQLite database detected. Use your managed database backup or pg_dump/mysqldump on the server.');

            return;
        }

        $databasePath = database_path('database.sqlite');

        if (File::exists($databasePath)) {
            File::copy($databasePath, "{$target}/database.sqlite");
        }
    }

    private function backupPublicStorage(string $target): void
    {
        $source = storage_path('app/public');
        $destination = "{$target}/storage-public";

        if (File::isDirectory($source)) {
            File::copyDirectory($source, $destination);
        }
    }
}
