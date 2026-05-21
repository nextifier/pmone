<?php

namespace App\Http\Controllers\Api\Payment;

use App\Contracts\Payment\ProvidesTransactions;
use App\Exceptions\Payment\PaymentProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReconcilePaymentRequest;
use App\Http\Resources\Payment\ReconciliationReportResource;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Services\Payment\PaymentProviderFactory;
use App\Services\Payment\PaymentReconciliationService;
use Illuminate\Http\JsonResponse;

class PaymentGatewayReconciliationController extends Controller
{
    public function __construct(
        private PaymentProviderFactory $providers,
        private PaymentReconciliationService $reconciliation,
    ) {}

    /**
     * Reconcile the gateway's successful payments against PM One reservations
     * for the requested date range.
     */
    public function index(
        ReconcilePaymentRequest $request,
        Project $project,
        ProjectPaymentGateway $paymentGateway,
    ): JsonResponse {
        if ($paymentGateway->project_id !== $project->id) {
            abort(404);
        }

        $provider = $this->providers->make($paymentGateway);

        if (! $provider instanceof ProvidesTransactions) {
            return response()->json([
                'message' => "The {$paymentGateway->provider} provider does not support reconciliation.",
                'error_code' => 'PROVIDER_CAPABILITY_UNSUPPORTED',
            ], 422);
        }

        try {
            $report = $this->reconciliation->reconcile(
                $provider,
                $project->id,
                $request->input('date_from'),
                $request->input('date_to'),
            );
        } catch (PaymentProviderException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => $e->errorCode,
            ], $e->httpStatus);
        }

        return ReconciliationReportResource::make($report)->response();
    }
}
