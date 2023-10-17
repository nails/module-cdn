<?php

namespace Nails\Cdn\Admin\Permission\Object;

use Nails\Admin\Interfaces\Permission;

class Usages implements Permission
{
    public function label(): string
    {
        return 'Can find where objects are used';
    }

    public function group(): string
    {
        return 'Objects';
    }
}
