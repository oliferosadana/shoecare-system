<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ProductionCheck extends Command
{
    protected $signature = 'app:production-check {--strict : Return failure when production requirements are not met}';

    protected $description = 'Check production readiness for environment, storage, database, assets, and integrations.';

    private int $failures = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $strict = (bool) $this->option('strict');

        $this->info('Running ZOLIX production readiness check...');

        $this->checkEnvironment($strict);
        $this->checkDatabase();
        $this->checkStorage();
        $this->checkAssets();
        $this->checkAutoGopay($strict);
        $this->checkAdminUser($strict);
        $this->checkSecurityFiles();

        $this->newLine();
        $this->line("Warnings: {$this->warnings}");
        $this->line("Failures: {$this->failures}");

        if ($this->failures > 0) {
            $this->error('Production check failed.');

            return self::FAILURE;
        }

        $this->info('Production check completed.');

        return self::SUCCESS;
    }

    private function checkEnvironment(bool $strict): void
    {
        $this->section('Environment');

        $this->expect(config('app.key') !== '', 'APP_KEY is set.', 'APP_KEY is missing.', true);
        $this->expect(config('app.debug') === false, 'APP_DEBUG is false.', 'APP_DEBUG should be false in production.', $strict);
        $this->expect(app()->environment('production'), 'APP_ENV is production.', 'APP_ENV is not production.', $strict);
        $this->expect(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL uses HTTPS.', 'APP_URL should use HTTPS in production.', $strict);
        $this->expect(config('session.encrypt') === true, 'Session encryption is enabled.', 'SESSION_ENCRYPT should be true in production.', $strict);
        $this->expect(config('session.secure') === true, 'Secure session cookies are enabled.', 'SESSION_SECURE_COOKIE should be true in production.', $strict);
    }

    private function checkDatabase(): void
    {
        $this->section('Database');

        try {
            DB::connection()->getPdo();
            $this->pass('Database connection works.');
        } catch (\Throwable $exception) {
            $this->failCheck('Database connection failed: ' . $exception->getMessage());

            return;
        }

        $this->expect(Schema::hasTable('orders'), 'Orders table exists.', 'Orders table missing.', true);
        $this->expect(Schema::hasTable('payments'), 'Payments table exists.', 'Payments table missing.', true);
        $this->expect(Schema::hasTable('customers'), 'Customers table exists.', 'Customers table missing.', true);

        $this->checkDatabaseBackedDrivers();
    }

    private function checkDatabaseBackedDrivers(): void
    {
        if (config('session.driver') === 'database') {
            $this->expect(
                Schema::hasTable((string) config('session.table', 'sessions')),
                'Database session table exists.',
                'Session driver is database, but the sessions table is missing.',
                true
            );
        }

        if (config('cache.default') === 'database') {
            $cacheStore = config('cache.stores.database', []);
            $cacheTable = (string) ($cacheStore['table'] ?? 'cache');

            $this->expect(
                Schema::hasTable($cacheTable),
                'Database cache table exists.',
                'Cache store is database, but the cache table is missing.',
                true
            );
        }

        if (config('queue.default') === 'database') {
            $queueConnection = config('queue.connections.database', []);
            $jobsTable = (string) ($queueConnection['table'] ?? 'jobs');

            $this->expect(
                Schema::hasTable($jobsTable),
                'Database queue jobs table exists.',
                'Queue connection is database, but the jobs table is missing.',
                true
            );
        }

        if (config('queue.failed.driver') === 'database-uuids') {
            $failedJobsTable = (string) config('queue.failed.table', 'failed_jobs');

            $this->expect(
                Schema::hasTable($failedJobsTable),
                'Failed jobs table exists.',
                'Failed queue driver stores to database, but failed_jobs table is missing.',
                true
            );
        }
    }

    private function checkStorage(): void
    {
        $this->section('Storage');

        $this->expect(config('filesystems.default') === 'public', 'Default filesystem is public.', 'FILESYSTEM_DISK should be public for uploaded photos.', false);
        $this->expect(File::isDirectory(storage_path('app/public')), 'Public storage directory exists.', 'storage/app/public is missing.', true);
        $this->expect(File::exists(public_path('storage')), 'Public storage link/path exists.', 'Run php artisan storage:link.', true);
        $this->expect(is_writable(storage_path('app/public')), 'Public storage is writable.', 'storage/app/public is not writable.', true);
    }

    private function checkAssets(): void
    {
        $this->section('Assets');

        $this->expect(File::exists(public_path('build/manifest.json')), 'Vite manifest exists.', 'Run npm run build.', true);
        $this->expect(! File::exists(public_path('hot')), 'Vite hot file is absent.', 'Delete public/hot for production.', true);
    }

    private function checkAutoGopay(bool $strict): void
    {
        $this->section('AutoGopay');

        $this->expect((string) config('services.autogopay.base_url') !== '', 'AutoGopay base URL is set.', 'AUTOGOPAY_BASE_URL is missing.', true);
        $this->expect((string) config('services.autogopay.token') !== '', 'AutoGopay token is set.', 'AUTOGOPAY_TOKEN is missing.', $strict);
    }

    private function checkAdminUser(bool $strict): void
    {
        $this->section('Admin User');

        try {
            $hasAdmin = User::where('role', 'admin')->where('is_active', true)->exists();
            $this->expect($hasAdmin, 'Active admin user exists.', 'Create admin with php artisan app:create-admin.', $strict);
        } catch (\Throwable $exception) {
            $this->warnCheck('Could not check admin user: ' . $exception->getMessage());
        }
    }

    private function checkSecurityFiles(): void
    {
        $this->section('Security');

        $this->expect(File::exists(storage_path('app/public/.htaccess')), 'Public upload .htaccess exists.', 'Add upload .htaccess protection for Apache servers.', false);
        $this->expect(File::exists(base_path('PRODUCTION.md')), 'Production checklist exists.', 'PRODUCTION.md is missing.', false);
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("<comment>{$title}</comment>");
    }

    private function expect(bool $condition, string $success, string $failure, bool $required): void
    {
        if ($condition) {
            $this->pass($success);

            return;
        }

        if ($required) {
            $this->failCheck($failure);

            return;
        }

        $this->warnCheck($failure);
    }

    private function pass(string $message): void
    {
        $this->line("<info>OK</info> {$message}");
    }

    private function warnCheck(string $message): void
    {
        $this->warnings++;
        $this->line("<comment>WARN</comment> {$message}");
    }

    private function failCheck(string $message): void
    {
        $this->failures++;
        $this->line("<error>FAIL</error> {$message}");
    }
}
