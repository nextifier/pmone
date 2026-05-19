<?php

namespace App\Jobs\Hotel;

use App\Services\Hotel\AllotmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReleaseExpiredAllotmentsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(AllotmentService $allotments): int
    {
        $released = $allotments->releaseExpiredAllotments();

        if ($released > 0) {
            activity()
                ->event('allotments_released')
                ->withProperties([
                    'released_count' => $released,
                ])
                ->log("Released {$released} expired allotment(s)");
        }

        return $released;
    }
}
