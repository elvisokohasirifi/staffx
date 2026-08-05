<?php

namespace App;

enum UserRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Admin->value => 'Admin',
            self::Staff->value => 'Staff',
        ];
    }
}
