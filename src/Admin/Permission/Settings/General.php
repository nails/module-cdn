<?php

namespace Nails\Cdn\Admin\Permission\Settings;

use Nails\Admin\Interfaces\Permission;

class General implements Permission
{
    public function label(): string
    {
        return 'Can manage CDN settings';
    }

    public function group(): string
    {
        return 'Settings';
    }
}
