<?php

namespace App\Observers;

use App\Models\ProjectPaymentGateway;
use App\Services\Payment\PaymentBalanceCache;
use App\Services\Xendit\XenditService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\ResponseCache\Facades\ResponseCache;

class ProjectPaymentGatewayObserver
{
    public function created(ProjectPaymentGateway $projectPaymentGateway): void
    {
        $this->forgetCaches($projectPaymentGateway);
    }

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

        // The public project payload exposes has_active_payment_gateway /
        // has_xendit_gateway, derived from these rows. Gateway writes do not
        // touch the Project model, so bust the 'projects'-tagged cache here.
        // Deferred to afterCommit so a concurrent request cannot re-cache the
        // pre-commit payload (observer events fire inside the transaction).
        DB::afterCommit(fn () => ResponseCache::clear(['projects']));
    }
}
