<?php

namespace Nails\Cdn\Admin\Permission\Bucket;

use Nails\Admin\Interfaces\Permission;

class Create implements Permission
{
    public function label(): string
    {
        return 'Can create buckets';
    }

    public function group(): string
    {
        return 'Buckets';
    }
}
