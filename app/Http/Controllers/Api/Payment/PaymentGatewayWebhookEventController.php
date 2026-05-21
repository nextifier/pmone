<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexPaymentWebhookEventRequest;
use App\Http\Resources\Payment\PaymentWebhookEventResource;
use App\Models\PaymentWebhookEvent;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentGatewayWebhookEventController extends Controller
{
    /**
     * List the inbound webhook events PM One recorded for a project's gateway
     * provider. Standard offset pagination (these rows live in our own DB).
     */
    public function index(
        IndexPaymentWebhookEventRequest $request,
        Project $project,
        ProjectPaymentGateway $paymentGateway,
    ): AnonymousResourceCollection {
        if ($paymentGateway->project_id !== $project->id) {
            abort(404);
        }

        $events = PaymentWebhookEvent::query()
            ->where('project_id', $project->id)
            ->forProvider($paymentGateway->provider)
            ->when(
                $request->input('status'),
                fn ($query, $status) => $query->where('status', $status),
            )
            ->latest()
            ->paginate(20);

        return PaymentWebhookEventResource::collection($events);
    }
}
