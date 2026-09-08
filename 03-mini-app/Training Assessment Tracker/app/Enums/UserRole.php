<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case Member = 'member';

    public function isAdministrator(): bool
    {
        return $this === self::Administrator;
    }
}
