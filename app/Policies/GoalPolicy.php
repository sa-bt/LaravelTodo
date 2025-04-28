<?php

namespace App\Policies;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GoalPolicy
{
    public function view(User $user, Goal $goal)
    {
        return $user->id === $goal->user_id;
    }

    public function update(User $user, Goal $goal)
    {
        return $user->id === $goal->user_id;
    }

    public function delete(User $user, Goal $goal)
    {
        return $user->id === $goal->user_id;
    }
}
