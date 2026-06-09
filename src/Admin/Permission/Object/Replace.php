<?php

namespace Nails\Cdn\Admin\Permission\Object;

use Nails\Admin\Interfaces\Permission;

class Replace implements Permission
{
    public function label(): string
    {
        return 'Can replace objects';
    }

    public function group(): string
    {
        return 'Objects';
    }
}
