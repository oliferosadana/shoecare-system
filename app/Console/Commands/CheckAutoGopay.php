<?php

namespace App\Console\Commands;

use App\Services\AutoGopayClient;
use Illuminate\Console\Command;
use Throwable;

class CheckAutoGopay extends Command
{
    protected $signature = 'app:autogopay-check {transaction_id=__invalid_test_transaction__}';

    protected $description = 'Check AutoGopay API connectivity and token using QRIS status endpoint.';

    public function handle(AutoGopayClient $client): int
    {
        try {
            $response = $client->checkStatus((string) $this->argument('transaction_id'));
            $this->info('AutoGopay reachable.');
            $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('AutoGopay check failed.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }
    }
}
