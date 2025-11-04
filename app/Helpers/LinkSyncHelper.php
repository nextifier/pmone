<?php

namespace App\Helpers;

use App\Models\Link;
use App\Models\Project;
use App\Models\User;

class LinkSyncHelper
{
    /**
     * Check if a label is a contact link (Email or WhatsApp)
     */
    public static function isContactLink(string $label): bool
    {
        return $label === 'Email' || $label === 'WhatsApp' || str_starts_with($label, 'WhatsApp ');
    }

    /**
     * Sync Email and WhatsApp links for a User
     */
    public static function syncUserContactLinks(User $user): void
    {
        // Refresh links to get latest state
        $user->load('links');
        $existingLinks = $user->links;
        $maxOrder = $existingLinks->max('order') ?? -1;

        // Sync Email link
        if ($user->email) {
            $emailLink = $existingLinks->firstWhere('label', 'Email');

            if (! $emailLink) {
                $user->links()->create([
                    'label' => 'Email',
                    'url' => "mailto:{$user->email}",
                    'order' => ++$maxOrder,
                    'is_active' => true,
                ]);
            } else {
                // Update URL if email changed
                $expectedUrl = "mailto:{$user->email}";
                if ($emailLink->url !== $expectedUrl) {
                    $emailLink->update(['url' => $expectedUrl]);
                }
            }
        } else {
            // Remove Email link if email is empty
            $existingLinks->where('label', 'Email')->each->delete();
        }

        // Sync WhatsApp link
        if ($user->phone) {
            $whatsappLink = $existingLinks->firstWhere('label', 'WhatsApp');
            $cleanPhone = preg_replace('/\D/', '', $user->phone);

            if (! $whatsappLink) {
                $user->links()->create([
                    'label' => 'WhatsApp',
                    'url' => "https://wa.me/{$cleanPhone}",
                    'order' => ++$maxOrder,
                    'is_active' => true,
                ]);
            } else {
                // Update URL if phone changed
                $expectedUrl = "https://wa.me/{$cleanPhone}";
                if ($whatsappLink->url !== $expectedUrl) {
                    $whatsappLink->update(['url' => $expectedUrl]);
                }
            }
        } else {
            // Remove WhatsApp link if phone is empty
            $existingLinks->where('label', 'WhatsApp')->each->delete();
        }
    }

    /**
     * Sync Email and WhatsApp links for a Project
     */
    public static function syncProjectContactLinks(Project $project): void
    {
        // Refresh links to get latest state
        $project->load('links');
        $existingLinks = $project->links;
        $maxOrder = $existingLinks->max('order') ?? -1;

        // Sync Email link
        if ($project->email) {
            $emailLink = $existingLinks->firstWhere('label', 'Email');

            if (! $emailLink) {
                $project->links()->create([
                    'label' => 'Email',
                    'url' => "mailto:{$project->email}",
                    'order' => ++$maxOrder,
                    'is_active' => true,
                ]);
            } else {
                // Update URL if email changed
                $expectedUrl = "mailto:{$project->email}";
                if ($emailLink->url !== $expectedUrl) {
                    $emailLink->update(['url' => $expectedUrl]);
                }
            }
        } else {
            // Remove Email link if email is empty
            $existingLinks->where('label', 'Email')->each->delete();
        }

        // Sync WhatsApp links (Project can have multiple phones)
        if ($project->phone && is_array($project->phone)) {
            $phoneNumbers = collect($project->phone);

            // Get existing WhatsApp links
            $existingWhatsappLinks = $existingLinks->filter(function ($link) {
                return $link->label === 'WhatsApp' || str_starts_with($link->label, 'WhatsApp ');
            });

            // Remove WhatsApp links that no longer exist in phone array
            $currentPhoneLabels = $phoneNumbers->pluck('label')->filter();
            $existingWhatsappLinks->each(function ($link) use ($currentPhoneLabels) {
                if (! $currentPhoneLabels->contains($link->label) && $link->label !== 'WhatsApp') {
                    $link->delete();
                }
            });

            // Add or update WhatsApp links
            foreach ($phoneNumbers as $phone) {
                $label = $phone['label'] ?? 'WhatsApp';
                $number = $phone['number'] ?? '';
                $cleanPhone = preg_replace('/\D/', '', $number);

                if (empty($cleanPhone)) {
                    continue;
                }

                $whatsappLink = $existingLinks->firstWhere('label', $label);
                $expectedUrl = "https://wa.me/{$cleanPhone}";

                if (! $whatsappLink) {
                    $project->links()->create([
                        'label' => $label,
                        'url' => $expectedUrl,
                        'order' => ++$maxOrder,
                        'is_active' => true,
                    ]);
                } else {
                    // Update URL if phone changed
                    if ($whatsappLink->url !== $expectedUrl) {
                        $whatsappLink->update(['url' => $expectedUrl]);
                    }
                }
            }
        } else {
            // Remove all WhatsApp links if no phones
            $existingLinks->filter(function ($link) {
                return $link->label === 'WhatsApp' || str_starts_with($link->label, 'WhatsApp ');
            })->each->delete();
        }
    }
}
