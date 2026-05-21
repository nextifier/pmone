<?php

namespace App\Http\Controllers\Api\Payment;

use App\Contracts\Payment\ProvidesBalance;
use App\Exceptions\Payment\PaymentProviderException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Payment\BalanceResource;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Services\Payment\PaymentBalanceCache;
use App\Services\Payment\PaymentProviderFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PaymentGatewayBalanceController extends Controller
{
    public function __construct(private PaymentProviderFactory $providers) {}

    /**
     * Return the current balance for a project's payment gateway.
     *
     * The result is cached per gateway for PaymentBalanceCache::TTL; passing
     * `?refresh=1` forces a live fetch and refreshes the cache.
     */
    public function show(Request $request, Project $project, ProjectPaymentGateway $paymentGateway): JsonResponse
    {
        if (! $request->user()?->can('payment_gateways.view_balance')) {
            abort(403);
        }

        if ($paymentGateway->project_id !== $project->id) {
            abort(404);
        }

        $provider = $this->providers->make($paymentGateway);

        if (! $provider instanceof ProvidesBalance) {
            return response()->json([
                'message' => "The {$paymentGateway->provider} provider does not support balance lookups.",
                'error_code' => 'PROVIDER_CAPABILITY_UNSUPPORTED',
            ], 422);
        }

        $cacheKey = PaymentBalanceCache::key($paymentGateway->id);

        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        try {
            // Cache the resolved primitive array, never the BalanceSnapshot
            // object: a serializing cache driver (file/redis/database) cannot
            // safely round-trip the DTO across requests.
            $balance = Cache::remember(
                $cacheKey,
                PaymentBalanceCache::TTL,
                fn (): array => BalanceResource::make($provider->getBalance())->resolve($request),
            );
        } catch (PaymentProviderException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => $e->errorCode,
            ], $e->httpStatus);
        }

        return response()->json(['data' => $balance]);
    }
}
