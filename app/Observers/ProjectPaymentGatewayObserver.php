<?php

namespace App\Observers;

use App\Models\ProjectPaymentGateway;
use App\Services\Payment\PaymentBalanceCache;
use App\Services\Xendit\XenditService;
use Illuminate\Support\Facades\Cache;

class ProjectPaymentGatewayObserver
{
    public function updated(ProjectPaymentGateway $projectPaymentGateway): void
    {
        $this->forgetCaches($projectPaymentGateway);
    }

    public function deleted(ProjectPaymentGateway $projectPaymentGateway): void
    {
        $this->forgetCaches($projectPaymentGateway);
    }

    public function restored(ProjectPaymentGateway $projectPaymentGateway): void
    {
        $this->forgetCaches($projectPaymentGateway);
    }

    public function forceDeleted(ProjectPaymentGateway $projectPaymentGateway): void
    {
        $this->forgetCaches($projectPaymentGateway);
    }

    /**
     * Drop every per-gateway cache entry so a credential or status change
     * never serves a stale payment-channel list or balance snapshot.
     */
    protected function forgetCaches(ProjectPaymentGateway $gateway): void
    {
        Cache::forget(PaymentBalanceCache::key($gateway->id));

        if ($gateway->provider === 'xendit') {
            Cache::forget(XenditService::PAYMENT_CHANNELS_CACHE_PREFIX."gateway:{$gateway->id}");
        }
    }
}
