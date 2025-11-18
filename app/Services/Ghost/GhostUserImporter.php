<?php

namespace App\Services\Ghost;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GhostUserImporter
{
    protected int $created = 0;

    protected int $skipped = 0;

    protected array $errors = [];

    public function __construct(
        protected GhostImporter $importer
    ) {}

    public function import(): array
    {
        $users = $this->importer->getData('users');

        foreach ($users as $ghostUser) {
            try {
                $this->importUser($ghostUser);
            } catch (\Exception $e) {
                $this->errors[] = [
                    'email' => $ghostUser['email'],
                    'error' => $e->getMessage(),
                ];
                Log::error('Failed to import Ghost user', [
                    'email' => $ghostUser['email'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'created' => $this->created,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
        ];
    }

    protected function importUser(array $ghostUser): void
    {
        // Check if user already exists
        $existingUser = User::query()->where('email', $ghostUser['email'])->first();

        if ($existingUser) {
            $this->skipped++;
            $this->importer->setMapping('users', $ghostUser['id'], $existingUser->id);
            Log::info('User already exists, skipping', ['email' => $ghostUser['email']]);

            return;
        }

        // Generate username from slug or email
        $username = $this->generateUniqueUsername($ghostUser['slug']);

        // Create user
        $user = User::create([
            'name' => $ghostUser['name'],
            'username' => $username,
            'email' => $ghostUser['email'],
            'email_verified_at' => now(), // Auto-verify imported users
            'password' => Hash::make('password'),
            'bio' => $ghostUser['bio'],
            'status' => $ghostUser['status'] === 'active' ? 'active' : 'inactive',
            'visibility' => $this->mapVisibility($ghostUser['visibility']),
            'created_at' => $ghostUser['created_at'],
            'updated_at' => $ghostUser['updated_at'],
        ]);

        // Store mapping
        $this->importer->setMapping('users', $ghostUser['id'], $user->id);

        $this->created++;

        Log::info('User imported successfully', [
            'ghost_id' => $ghostUser['id'],
            'pmone_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    protected function generateUniqueUsername(string $slug): string
    {
        $username = Str::slug($slug);
        $original = $username;
        $counter = 1;

        // Check both User table and ShortLink table to avoid conflicts
        while (
            User::query()->where('username', $username)->exists() ||
            \App\Models\ShortLink::query()->where('slug', $username)->exists()
        ) {
            $username = $original.'-'.$counter;
            $counter++;

            // Safety: avoid infinite loop
            if ($counter > 100) {
                $username = $original.'-'.Str::random(6);
                break;
            }
        }

        return $username;
    }

    protected function mapVisibility(string $visibility): string
    {
        return match ($visibility) {
            'public' => 'public',
            default => 'private',
        };
    }
}
