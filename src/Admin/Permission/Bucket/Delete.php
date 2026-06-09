<?php

namespace Nails\Cdn\Admin\Permission\Bucket;

use Nails\Admin\Interfaces\Permission;

class Delete implements Permission
{
    public function label(): string
    {
        return 'Can delete buckets';
    }

    public function group(): string
    {
        return 'Buckets';
    }
}
