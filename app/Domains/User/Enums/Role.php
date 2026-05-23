<?php

namespace App\Domains\User\Enums;

enum Role: string
{
    case Admin = 'admin';
    case User = 'user';
}
