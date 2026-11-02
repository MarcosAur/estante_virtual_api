<?php

namespace App\Policies;

use App\Models\MovieReview;
use App\Models\User;

class MovieReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MovieReview $review): bool
    {
        return $review->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, MovieReview $review): bool
    {
        return $review->user_id === $user->id;
    }

    public function delete(User $user, MovieReview $review): bool
    {
        return $review->user_id === $user->id;
    }
}
