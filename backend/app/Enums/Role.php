<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Operator = 'operator';
    case Inspector = 'inspector';
}
