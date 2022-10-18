<?php

namespace Nails\Cdn\Admin\Permission\Object;

use Nails\Admin\Interfaces\Permission;

class Restore implements Permission
{
    public function label(): string
    {
        return 'Can restore objects';
    }

    public function group(): string
    {
        return 'Objects';
    }
}
