<?php

namespace Nails\Cdn\Resource\CdnObject;

use Nails\Common\Resource;

/**
 * Class Url
 *
 * @package Nails\Cdn\Resource\CdnObject
 */
class Url extends Resource
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var bool
     */
    protected $is_img;

    /**
     * @var string
     */
    public $src;

    /**
     * @var string
     */
    public $download;

    // --------------------------------------------------------------------------

    /**
     * Url constructor.
     *
     * @param array $mObj
     */
    public function __construct($mObj = [])
    {
        parent::__construct($mObj);
        $this->src      = cdnServe($this->id);
        $this->download = cdnServe($this->id, true);
    }
}
