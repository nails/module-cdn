<?php

namespace Nails\Cdn\Admin\Permission\Object\Trash;

use Nails\Admin\Interfaces\Permission;

class Browse implements Permission
{
    public function label(): string
    {
        return 'Can browse trashed objects';
    }

    public function group(): string
    {
        return 'Objects';
    }
}
