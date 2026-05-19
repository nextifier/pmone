<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectPaymentGatewayRequest;
use App\Http\Requests\TestProjectPaymentGatewayRequest;
use App\Http\Requests\UpdateProjectPaymentGatewayRequest;
use App\Http\Resources\ProjectPaymentGatewayResource;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Services\Xendit\XenditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectPaymentGatewayController extends Controller
{
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorizeView();

        $gateways = $project->paymentGateways()
            ->orderByDesc('is_active')
            ->orderBy('provider')
            ->orderBy('mode')
            ->get();

        return ProjectPaymentGatewayResource::collection($gateways);
    }

    public function store(StoreProjectPaymentGatewayRequest $request, Project $project): JsonResponse
    {
        $data = $request->validated();
        $data['project_id'] = $project->id;

        $gateway = $project->paymentGateways()->create($data);

        activity()
            ->causedBy($request->user())
            ->performedOn($gateway)
            ->event('payment_gateway_added')
            ->withProperties([
                'project_id' => $project->id,
                'provider' => $gateway->provider,
                'mode' => $gateway->mode,
                'is_active' => (bool) $gateway->is_active,
            ])
            ->log("Payment gateway added: {$gateway->provider} ({$gateway->mode})");

        return (new ProjectPaymentGatewayResource($gateway->fresh()->load('project')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Project $project, ProjectPaymentGateway $paymentGateway): ProjectPaymentGatewayResource
    {
        $this->authorizeView();
        $this->ensureBelongsToProject($project, $paymentGateway);

        return new ProjectPaymentGatewayResource($paymentGateway->load('project'));
    }

    public function update(
        UpdateProjectPaymentGatewayRequest $request,
        Project $project,
        ProjectPaymentGateway $paymentGateway,
    ): ProjectPaymentGatewayResource {
        $this->ensureBelongsToProject($project, $paymentGateway);

        $data = $request->validated();

        // Empty string for credential fields = "keep existing".
        $credentialsRotated = [];
        foreach (['secret_key', 'public_key', 'webhook_token'] as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === '' || $data[$field] === null)) {
                unset($data[$field]);
            } elseif (array_key_exists($field, $data)) {
                $credentialsRotated[] = $field;
            }
        }

        $paymentGateway->update($data);

        if (! empty($credentialsRotated)) {
            activity()
                ->causedBy($request->user())
                ->performedOn($paymentGateway)
                ->event('payment_gateway_credentials_rotated')
                ->withProperties([
                    'project_id' => $project->id,
                    'provider' => $paymentGateway->provider,
                    'mode' => $paymentGateway->mode,
                    'rotated_fields' => $credentialsRotated,
                ])
                ->log("Payment gateway credentials rotated: {$paymentGateway->provider}");
        }

        return new ProjectPaymentGatewayResource($paymentGateway->fresh()->load('project'));
    }

    /**
     * Verify provider credentials BEFORE persisting them. Issues a single
     * authenticated read-only call (Xendit: GET /payment_channels) using the
     * candidate `secret_key`. Returns either a success summary or a mapped
     * error so the admin UI can surface the precise failure mode (bad key,
     * wrong account, IP allowlist gap, etc.).
     */
    public function testConnection(
        TestProjectPaymentGatewayRequest $request,
        Project $project,
        XenditService $xendit,
    ): JsonResponse {
        $data = $request->validated();

        // Quick structural pre-checks — fail fast before round-tripping to
        // Xendit on obviously-wrong inputs so we don't burn rate limit.
        if ($data['provider'] === 'xendit' && ! str_starts_with($data['secret_key'], 'xnd_')) {
            return response()->json([
                'success' => false,
                'error_code' => 'PAYMENT_GATEWAY_MISCONFIGURED',
                'message' => 'Xendit secret keys start with "xnd_". Double-check the value you pasted.',
            ], 422);
        }

        $result = $xendit->testCredentials(
            secretKey: $data['secret_key'],
            webhookToken: $data['webhook_token'] ?? null,
        );

        $webhook = $data['webhook_token'] ?? null;
        if ($webhook !== null && trim($webhook) !== '') {
            $result['webhook_token'] = [
                'ok' => strlen($webhook) >= 16,
                'message' => strlen($webhook) >= 16
                    ? 'Webhook token format looks valid.'
                    : 'Webhook token looks too short. Make sure it matches the value in Xendit → Settings → Webhooks.',
            ];
        }

        activity()
            ->causedBy($request->user())
            ->event('payment_gateway_test_connection')
            ->withProperties([
                'project_id' => $project->id,
                'provider' => $data['provider'],
                'mode' => $data['mode'],
                'success' => $result['success'],
                'error_code' => $result['error_code'] ?? null,
            ])
            ->log("Payment gateway test connection: {$data['provider']} ({$data['mode']}) - ".($result['success'] ? 'success' : ($result['error_code'] ?? 'failed')));

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function destroy(Project $project, ProjectPaymentGateway $paymentGateway): JsonResponse
    {
        if (! auth()->user()?->can('payment_gateways.delete')) {
            abort(403);
        }

        $this->ensureBelongsToProject($project, $paymentGateway);

        $provider = $paymentGateway->provider;
        $mode = $paymentGateway->mode;

        $paymentGateway->delete();

        activity()
            ->causedBy(auth()->user())
            ->event('payment_gateway_removed')
            ->withProperties([
                'project_id' => $project->id,
                'provider' => $provider,
                'mode' => $mode,
            ])
            ->log("Payment gateway removed: {$provider} ({$mode})");

        return response()->json(['message' => 'Payment gateway deleted.']);
    }

    private function authorizeView(): void
    {
        if (! auth()->user()?->can('payment_gateways.read')) {
            abort(403);
        }
    }

    private function ensureBelongsToProject(Project $project, ProjectPaymentGateway $paymentGateway): void
    {
        if ($paymentGateway->project_id !== $project->id) {
            abort(404);
        }
    }
}
