<?php

namespace Nails\Cdn\Admin\Permission\Bucket;

use Nails\Admin\Interfaces\Permission;

class Edit implements Permission
{
    public function label(): string
    {
        return 'Can edit buckets';
    }

    public function group(): string
    {
        return 'Buckets';
    }
}
