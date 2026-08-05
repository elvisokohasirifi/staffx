<?php

namespace App\Policies;

use App\Models\TaskRemark;
use App\Models\User;

class TaskRemarkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    public function view(User $user, TaskRemark $taskRemark): bool
    {
        return $user->isAdmin() || $taskRemark->task->assignee_id === $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    public function update(User $user, TaskRemark $taskRemark): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, TaskRemark $taskRemark): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, TaskRemark $taskRemark): bool
    {
        return false;
    }

    public function forceDelete(User $user, TaskRemark $taskRemark): bool
    {
        return false;
    }
}
