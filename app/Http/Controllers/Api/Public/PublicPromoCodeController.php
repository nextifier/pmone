<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Promo\ValidateCodeRequest;
use App\Services\Promotion\PromoCodeService;
use App\Services\Reservation\TransientReservationBuilder;
use Illuminate\Http\JsonResponse;

class PublicPromoCodeController extends Controller
{
    public function __construct(
        protected PromoCodeService $promoCodes,
        protected TransientReservationBuilder $transientReservation,
    ) {}

    /**
     * Validate a promo code against a hypothetical purchase payload.
     *
     * No persistence: builds a transient unsaved Reservation/Order model from
     * the supplied payload and runs validate() against it. Returns the standard
     * PromoCodeValidation shape with preview_discount and preview_total.
     */
    public function validate(ValidateCodeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $targetType = $data['target_type'];

        $entity = match ($targetType) {
            'Reservation' => $this->transientReservation->build($data['payload']),
            'Order' => abort(422, 'Order target preview not yet supported in public flow.'),
        };

        $validation = $this->promoCodes->validate(
            $data['code'],
            $entity,
            $data['email'],
            null,
        );

        return response()->json([
            'data' => $validation->toArray(),
        ], $validation->valid ? 200 : 422);
    }
}
