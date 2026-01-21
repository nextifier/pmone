<?php

namespace App\Console\Commands;

use App\Jobs\FetchExchangeRates;
use Illuminate\Console\Command;

class FetchExchangeRatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exchange-rate:fetch
                            {--sync : Run synchronously instead of queuing}
                            {--currency=USD : Base currency to fetch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch exchange rates from external API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $currency = $this->option('currency');
        $sync = $this->option('sync');

        $this->info("Fetching exchange rates for base currency: {$currency}");

        if ($sync) {
            $this->info('Running synchronously...');
            (new FetchExchangeRates($currency))->handle();
            $this->info('Exchange rates fetched successfully!');
        } else {
            FetchExchangeRates::dispatch($currency);
            $this->info('Exchange rate fetch job dispatched to queue.');
        }

        return Command::SUCCESS;
    }
}
