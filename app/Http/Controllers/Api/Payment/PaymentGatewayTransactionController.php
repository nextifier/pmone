<?php

namespace App\Http\Controllers\Api\Payment;

use App\Contracts\Payment\ProvidesTransactions;
use App\DTOs\Payment\TransactionQuery;
use App\Exceptions\Payment\PaymentProviderException;
use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexPaymentTransactionRequest;
use App\Http\Resources\Payment\TransactionResource;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Services\Payment\PaymentProviderFactory;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentGatewayTransactionController extends Controller
{
    public function __construct(private PaymentProviderFactory $providers) {}

    /**
     * List transactions for a project's payment gateway. Cursor-paginated:
     * pass `after_id` (from the previous response's `meta.next_cursor`) to
     * load the next page.
     */
    public function index(
        IndexPaymentTransactionRequest $request,
        Project $project,
        ProjectPaymentGateway $paymentGateway,
    ): JsonResponse {
        if ($paymentGateway->project_id !== $project->id) {
            abort(404);
        }

        $provider = $this->providers->make($paymentGateway);

        if (! $provider instanceof ProvidesTransactions) {
            return response()->json([
                'message' => "The {$paymentGateway->provider} provider does not support transaction listing.",
                'error_code' => 'PROVIDER_CAPABILITY_UNSUPPORTED',
            ], 422);
        }

        $query = new TransactionQuery(
            limit: $request->integer('limit') ?: 15,
            afterId: $request->input('after_id'),
            type: $request->input('type'),
            status: $request->input('status'),
            dateFrom: $request->input('date_from'),
            dateTo: $request->input('date_to'),
        );

        try {
            $page = $provider->listTransactions($query);
        } catch (PaymentProviderException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => $e->errorCode,
            ], $e->httpStatus);
        }

        return TransactionResource::collection($page->entries)
            ->additional([
                'meta' => [
                    'has_more' => $page->hasMore,
                    'next_cursor' => $page->nextCursor,
                ],
            ])
            ->response();
    }

    /**
     * Export every transaction matching the current filters to Excel.
     * Walks the provider's cursor pages up to a safety cap so a filterless
     * export on a busy account cannot loop unbounded.
     */
    public function export(
        IndexPaymentTransactionRequest $request,
        Project $project,
        ProjectPaymentGateway $paymentGateway,
    ): BinaryFileResponse|JsonResponse {
        if ($paymentGateway->project_id !== $project->id) {
            abort(404);
        }

        $provider = $this->providers->make($paymentGateway);

        if (! $provider instanceof ProvidesTransactions) {
            return response()->json([
                'message' => "The {$paymentGateway->provider} provider does not support transaction listing.",
                'error_code' => 'PROVIDER_CAPABILITY_UNSUPPORTED',
            ], 422);
        }

        $rows = collect();
        $cursor = null;
        $maxPages = 50;

        try {
            for ($i = 0; $i < $maxPages; $i++) {
                $page = $provider->listTransactions(new TransactionQuery(
                    limit: 50,
                    afterId: $cursor,
                    type: $request->input('type'),
                    status: $request->input('status'),
                    dateFrom: $request->input('date_from'),
                    dateTo: $request->input('date_to'),
                ));

                $rows = $rows->concat($page->entries);

                if (! $page->hasMore || $page->nextCursor === null) {
                    break;
                }
                $cursor = $page->nextCursor;
            }
        } catch (PaymentProviderException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => $e->errorCode,
            ], $e->httpStatus);
        }

        $filename = 'transactions_'.$paymentGateway->provider.'_'.now()->format('Y-m-d_His').'.xlsx';

        activity()
            ->causedBy($request->user())
            ->event('exported')
            ->withProperties([
                'project_id' => $project->id,
                'model_type' => 'PaymentTransaction',
                'provider' => $paymentGateway->provider,
                'count' => $rows->count(),
                'filename' => $filename,
            ])
            ->log('Exported payment transactions for '.$paymentGateway->provider);

        return Excel::download(new TransactionsExport($rows), $filename);
    }
}
