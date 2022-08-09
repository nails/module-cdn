<?php

namespace Nails\Cdn\Admin\Permission\Object;

use Nails\Admin\Interfaces\Permission;

class FindOrphan implements Permission
{
    public function label(): string
    {
        return 'Can find orphaned objects';
    }

    public function group(): string
    {
        return 'Objects';
    }
}
