<?php

namespace Nails\Cdn\Admin\Permission\Object;

use Nails\Admin\Interfaces\Permission;

class Move implements Permission
{
    public function label(): string
    {
        return 'Can move objects';
    }

    public function group(): string
    {
        return 'Objects';
    }
}
