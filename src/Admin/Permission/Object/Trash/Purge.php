<?php

namespace Nails\Cdn\Admin\Permission\Object\Trash;

use Nails\Admin\Interfaces\Permission;

class Purge implements Permission
{
    public function label(): string
    {
        return 'Can purge deleted objects';
    }

    public function group(): string
    {
        return 'Objects';
    }
}
