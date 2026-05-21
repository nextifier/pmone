<?php

namespace App\Http\Controllers\Api\Payment;

use App\Contracts\Payment\ProvidesSettlements;
use App\Exceptions\Payment\PaymentProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShowPaymentSettlementRequest;
use App\Http\Resources\Payment\SettlementSummaryResource;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Services\Payment\PaymentProviderFactory;
use Illuminate\Http\JsonResponse;

class PaymentGatewaySettlementController extends Controller
{
    public function __construct(private PaymentProviderFactory $providers) {}

    /**
     * Return the settlement summary for a project's payment gateway.
     * Defaults to the last 30 days when no date range is given.
     */
    public function show(
        ShowPaymentSettlementRequest $request,
        Project $project,
        ProjectPaymentGateway $paymentGateway,
    ): JsonResponse {
        if ($paymentGateway->project_id !== $project->id) {
            abort(404);
        }

        $provider = $this->providers->make($paymentGateway);

        if (! $provider instanceof ProvidesSettlements) {
            return response()->json([
                'message' => "The {$paymentGateway->provider} provider does not support settlement tracking.",
                'error_code' => 'PROVIDER_CAPABILITY_UNSUPPORTED',
            ], 422);
        }

        $dateFrom = $request->input('date_from') ?? now()->subDays(30)->toDateString();
        $dateTo = $request->input('date_to') ?? now()->toDateString();

        try {
            $summary = $provider->getSettlementSummary($dateFrom, $dateTo);
        } catch (PaymentProviderException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => $e->errorCode,
            ], $e->httpStatus);
        }

        return SettlementSummaryResource::make($summary)
            ->additional(['meta' => ['date_from' => $dateFrom, 'date_to' => $dateTo]])
            ->response();
    }
}
