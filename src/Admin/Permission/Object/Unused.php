<?php

namespace Nails\Cdn\Admin\Permission\Object;

use Nails\Admin\Interfaces\Permission;

class Unused implements Permission
{
    public function label(): string
    {
        return 'Can find unused objects';
    }

    public function group(): string
    {
        return 'Objects';
    }
}
