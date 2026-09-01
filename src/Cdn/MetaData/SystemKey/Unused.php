<?php

namespace Nails\Cdn\Cdn\MetaData\SystemKey;

use Nails\Cdn\Interfaces\MetaData\SystemKey;

class Unused implements SystemKey
{
    public function get(): string
    {
        return 'cdn:monitor:unused';
    }
}
