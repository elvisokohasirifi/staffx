<?php

namespace App;

use Carbon\CarbonInterface;

enum RecurringTaskPattern: string
{
    case Weekdays = 'weekdays';
    case WeekdaysAndSaturday = 'weekdays_and_saturday';
    case WeekdaysAndSunday = 'weekdays_and_sunday';
    case Everyday = 'everyday';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Weekdays->value => 'Weekdays',
            self::WeekdaysAndSaturday->value => 'Weekdays + Saturday',
            self::WeekdaysAndSunday->value => 'Weekdays + Sunday',
            self::Everyday->value => 'Everyday',
        ];
    }

    public function appliesToDate(CarbonInterface $date): bool
    {
        return match ($this) {
            self::Weekdays => $date->isWeekday(),
            self::WeekdaysAndSaturday => $date->isWeekday() || $date->isSaturday(),
            self::WeekdaysAndSunday => $date->isWeekday() || $date->isSunday(),
            self::Everyday => true,
        };
    }
}
