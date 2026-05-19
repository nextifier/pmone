<?php

namespace App\Console\Commands\Xendit;

use App\Models\ProjectPaymentGateway;
use App\Services\Xendit\XenditService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('xendit:refresh-payment-channels {--gateway= : Refresh a single gateway by id} {--all : Refresh every active Xendit gateway}')]
#[Description('Flush cached Xendit payment channels and re-fetch from the API')]
class RefreshPaymentChannelsCommand extends Command
{
    public function handle(): int
    {
        $gatewayId = $this->option('gateway');
        $all = (bool) $this->option('all');

        if ($gatewayId !== null && $all) {
            $this->error('Use either --gateway or --all, not both.');

            return self::INVALID;
        }

        if ($gatewayId !== null) {
            $gateway = ProjectPaymentGateway::query()->forProvider('xendit')->find($gatewayId);
            if (! $gateway) {
                $this->error("Xendit gateway #{$gatewayId} not found.");

                return self::FAILURE;
            }

            return $this->refreshGateway($gateway);
        }

        if ($all) {
            $gateways = ProjectPaymentGateway::query()->forProvider('xendit')->active()->get();
            if ($gateways->isEmpty()) {
                $this->warn('No active Xendit gateways found.');

                return self::SUCCESS;
            }

            foreach ($gateways as $gateway) {
                $this->refreshGateway($gateway);
            }

            return self::SUCCESS;
        }

        $this->refreshLegacy();

        return self::SUCCESS;
    }

    protected function refreshGateway(ProjectPaymentGateway $gateway): int
    {
        $service = XenditService::forGateway($gateway);
        Cache::forget($service->paymentChannelsCacheKey());

        $logos = $service->getEnabledPaymentChannels();
        $this->info(sprintf(
            'Refreshed gateway #%d (%s): %d channel logos cached.',
            $gateway->id,
            $gateway->label ?? $gateway->ulid,
            count($logos),
        ));

        return self::SUCCESS;
    }

    protected function refreshLegacy(): void
    {
        /** @var XenditService $service */
        $service = app(XenditService::class);
        Cache::forget($service->paymentChannelsCacheKey());

        $logos = $service->getEnabledPaymentChannels();
        $this->info(sprintf('Refreshed legacy/env Xendit gateway: %d channel logos cached.', count($logos)));
    }
}
