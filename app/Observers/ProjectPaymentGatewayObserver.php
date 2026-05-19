<?php

namespace App\Observers;

use App\Models\ProjectPaymentGateway;
use App\Services\Xendit\XenditService;
use Illuminate\Support\Facades\Cache;

class ProjectPaymentGatewayObserver
{
    public function updated(ProjectPaymentGateway $projectPaymentGateway): void
    {
        $this->forgetPaymentChannelsCache($projectPaymentGateway);
    }

    public function deleted(ProjectPaymentGateway $projectPaymentGateway): void
    {
        $this->forgetPaymentChannelsCache($projectPaymentGateway);
    }

    public function restored(ProjectPaymentGateway $projectPaymentGateway): void
    {
        $this->forgetPaymentChannelsCache($projectPaymentGateway);
    }

    public function forceDeleted(ProjectPaymentGateway $projectPaymentGateway): void
    {
        $this->forgetPaymentChannelsCache($projectPaymentGateway);
    }

    protected function forgetPaymentChannelsCache(ProjectPaymentGateway $gateway): void
    {
        if ($gateway->provider !== 'xendit') {
            return;
        }

        Cache::forget(XenditService::PAYMENT_CHANNELS_CACHE_PREFIX."gateway:{$gateway->id}");
    }
}
