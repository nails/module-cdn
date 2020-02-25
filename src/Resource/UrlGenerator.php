<?php

namespace Nails\Cdn\Resource;

use Nails\Cdn\Interfaces;
use Nails\Cdn\Service;
use Nails\Common\Resource;

abstract class UrlGenerator extends Resource
{
    protected $oService;
    protected $iObjectId;

    // --------------------------------------------------------------------------

    public function __construct(Service\UrlGenerator $oService, int $iObjectId)
    {
        $this->oService  = $oService;
        $this->iObjectId = $iObjectId;
    }

    public function __toString()
    {
        dd(static::class, 'TO STRING!');
    }
}
