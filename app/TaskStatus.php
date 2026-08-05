<?php

namespace App;

enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case CouldNotBeAchieved = 'could_not_be_achieved';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Pending->value => 'Pending',
            self::InProgress->value => 'In Progress',
            self::Completed->value => 'Completed',
            self::CouldNotBeAchieved->value => 'Could Not Be Achieved',
        ];
    }
}
