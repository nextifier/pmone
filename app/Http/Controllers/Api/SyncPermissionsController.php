<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SyncPermissionsController extends Controller
{
    /**
     * Run `permissions:sync` to create any permissions defined in
     * config/permissions.php that are missing from the database and re-grant
     * them to the master role.
     *
     * Used after a deploy that adds new permissions, since a code deploy does
     * not seed the database. Lets an admin sync without SSH. Pruning is never
     * triggered here, so the operation is purely additive and safe to repeat.
     */
    public function sync(Request $request): JsonResponse
    {
        Artisan::call('permissions:sync', ['--no-interaction' => true]);

        activity()
            ->causedBy($request->user())
            ->event('permissions_synced')
            ->log('Synced permissions from config');

        return response()->json([
            'message' => 'Permissions synced successfully.',
        ]);
    }
}
