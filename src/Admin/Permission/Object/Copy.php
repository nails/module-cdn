<?php

namespace Nails\Cdn\Admin\Permission\Object;

use Nails\Admin\Interfaces\Permission;

class Copy implements Permission
{
    public function label(): string
    {
        return 'Can copy objects';
    }

    public function group(): string
    {
        return 'Objects';
    }
}
