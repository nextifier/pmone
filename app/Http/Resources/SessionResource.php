<?php

namespace App\Http\Resources;

use App\Support\UserAgentParser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Wraps a raw row from the `sessions` table (database session driver).
 */
class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lastActivity = $this->last_activity ? (int) $this->last_activity : null;

        return [
            'id' => $this->id,
            'ip_address' => $this->ip_address,
            'device' => UserAgentParser::parse($this->user_agent),
            'last_activity' => $lastActivity ? Carbon::createFromTimestamp($lastActivity)->toISOString() : null,
            'last_activity_human' => $lastActivity ? Carbon::createFromTimestamp($lastActivity)->diffForHumans() : null,
            'is_online' => $lastActivity !== null && $lastActivity > now()->subMinutes(5)->getTimestamp(),
            'is_current' => $request->hasSession() && $request->session()->getId() === $this->id,
        ];
    }
}
