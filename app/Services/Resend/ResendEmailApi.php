<?php

namespace App\Services\Resend;

use Resend\Laravel\Facades\Resend;

/**
 * Thin wrapper over the Resend Emails API. It exists so callers deal in plain
 * arrays (and so the whole provider can be swapped for a fake in tests), instead
 * of the SDK's resource objects.
 */
class ResendEmailApi
{
    /**
     * Retrieve a single email, including its html/text body, from Resend.
     *
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return Resend::emails()->get($id)->toArray();
    }

    /**
     * List sent emails newest-first. Resend paginates by cursor, so pass the id
     * of the last email seen as $after to fetch the following page.
     *
     * @return array{has_more: bool, data: list<array<string, mixed>>}
     */
    public function list(?string $after = null, int $limit = 100): array
    {
        $options = ['limit' => $limit];

        if ($after !== null) {
            $options['after'] = $after;
        }

        $collection = Resend::emails()->list($options);

        return [
            'has_more' => (bool) ($collection->has_more ?? false),
            'data' => array_map(
                fn ($email) => $email->toArray(),
                $collection->data ?? [],
            ),
        ];
    }
}
