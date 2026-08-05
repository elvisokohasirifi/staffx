<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    public function view(User $user, Task $task): bool
    {
        return $user->isAdmin() || $task->assignee_id === $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Task $task): bool
    {
        return $user->isAdmin() || $task->assignee_id === $user->getKey();
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Task $task): bool
    {
        return false;
    }

    public function forceDelete(User $user, Task $task): bool
    {
        return false;
    }
}
