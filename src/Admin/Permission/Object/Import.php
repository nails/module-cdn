<?php

namespace Nails\Cdn\Admin\Permission\Object;

use Nails\Admin\Interfaces\Permission;

class Import implements Permission
{
    public function label(): string
    {
        return 'Can import objects';
    }

    public function group(): string
    {
        return 'Objects';
    }
}
