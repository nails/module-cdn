<?php

namespace Nails\Cdn\Resource\CdnObject;

use Nails\Common\Resource;

/**
 * Class Image
 *
 * @package Nails\Cdn\Resource\CdnObject
 */
class Image extends Resource
{
    /**
     * @var int
     */
    public $width;

    /**
     * @var int
     */
    public $height;

    /**
     * @var string
     */
    public $orientation;

    /**
     * @var bool
     */
    public $animated;
}
