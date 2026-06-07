<?php

namespace App\Policies;

use App\Models\Faq;
use App\Models\User;

class FaqPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('faqs.read');
    }

    public function view(User $user, Faq $faq): bool
    {
        return $user->can('faqs.read');
    }

    public function create(User $user): bool
    {
        return $user->can('faqs.create');
    }

    public function update(User $user, Faq $faq): bool
    {
        return $user->can('faqs.update');
    }

    public function delete(User $user, Faq $faq): bool
    {
        return $user->can('faqs.delete');
    }

    public function restore(User $user, Faq $faq): bool
    {
        return $user->can('faqs.restore');
    }

    public function forceDelete(User $user, Faq $faq): bool
    {
        return $user->can('faqs.delete');
    }

    public function reorder(User $user): bool
    {
        return $user->can('faqs.update');
    }

    public function bulkUpdate(User $user): bool
    {
        return $user->can('faqs.update');
    }

    public function bulkDelete(User $user): bool
    {
        return $user->can('faqs.delete');
    }
}
