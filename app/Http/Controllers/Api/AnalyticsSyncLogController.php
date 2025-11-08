<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsSyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsSyncLogController extends Controller
{
    /**
     * Display a listing of sync logs.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'nullable|in:property,aggregate',
            'status' => 'nullable|in:started,success,failed',
            'hours' => 'nullable|integer|min:1|max:168', // Max 1 week
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AnalyticsSyncLog::query()
            ->with('property:id,name,property_id')
            ->orderBy('created_at', 'desc');

        // Filter by type
        if ($request->filled('type')) {
            $query->where('sync_type', $request->type);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by time range
        if ($request->filled('hours')) {
            $query->where('created_at', '>=', now()->subHours($request->hours));
        } else {
            // Default to last 24 hours
            $query->where('created_at', '>=', now()->subHours(24));
        }

        $limit = $request->integer('limit', 50);
        $logs = $query->limit($limit)->get();

        // Calculate statistics
        $stats = [
            'total' => $logs->count(),
            'success' => $logs->where('status', 'success')->count(),
            'failed' => $logs->where('status', 'failed')->count(),
            'in_progress' => $logs->where('status', 'started')->count(),
            'avg_duration' => $logs->where('status', 'success')
                ->avg('duration_seconds'),
        ];

        return response()->json([
            'logs' => $logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'sync_type' => $log->sync_type,
                    'property' => $log->property ? [
                        'id' => $log->property->id,
                        'name' => $log->property->name,
                        'property_id' => $log->property->property_id,
                    ] : null,
                    'days' => $log->days,
                    'status' => $log->status,
                    'started_at' => $log->started_at?->toIso8601String(),
                    'completed_at' => $log->completed_at?->toIso8601String(),
                    'duration_seconds' => $log->duration_seconds,
                    'metadata' => $log->metadata,
                    'error_message' => $log->error_message,
                    'created_at' => $log->created_at->toIso8601String(),
                ];
            }),
            'stats' => $stats,
        ]);
    }

    /**
     * Get sync statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $request->validate([
            'hours' => 'nullable|integer|min:1|max:168',
        ]);

        $hours = $request->integer('hours', 24);
        $since = now()->subHours($hours);

        $totalSyncs = AnalyticsSyncLog::where('created_at', '>=', $since)->count();
        $successfulSyncs = AnalyticsSyncLog::success()->where('created_at', '>=', $since)->count();
        $failedSyncs = AnalyticsSyncLog::failed()->where('created_at', '>=', $since)->count();
        $inProgressSyncs = AnalyticsSyncLog::where('status', 'started')
            ->where('created_at', '>=', $since)
            ->count();

        $propertySyncs = AnalyticsSyncLog::propertySyncs()
            ->where('created_at', '>=', $since)
            ->count();

        $aggregateSyncs = AnalyticsSyncLog::aggregateSyncs()
            ->where('created_at', '>=', $since)
            ->count();

        $avgDuration = AnalyticsSyncLog::success()
            ->where('created_at', '>=', $since)
            ->whereNotNull('duration_seconds')
            ->avg('duration_seconds');

        $lastSync = AnalyticsSyncLog::orderBy('created_at', 'desc')->first();

        return response()->json([
            'period_hours' => $hours,
            'total_syncs' => $totalSyncs,
            'successful_syncs' => $successfulSyncs,
            'failed_syncs' => $failedSyncs,
            'in_progress_syncs' => $inProgressSyncs,
            'property_syncs' => $propertySyncs,
            'aggregate_syncs' => $aggregateSyncs,
            'avg_duration_seconds' => $avgDuration ? round($avgDuration, 2) : null,
            'success_rate' => $totalSyncs > 0 ? round(($successfulSyncs / $totalSyncs) * 100, 2) : 0,
            'last_sync' => $lastSync ? [
                'id' => $lastSync->id,
                'sync_type' => $lastSync->sync_type,
                'status' => $lastSync->status,
                'created_at' => $lastSync->created_at->toIso8601String(),
            ] : null,
        ]);
    }
}
