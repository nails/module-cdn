<?php

namespace Nails\Cdn\Resource\CdnObject;

use Nails\Common\Model\Base;
use Nails\Common\Resource;
use stdClass;

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
     */
    public function __construct(self|stdClass|array $resource = [], ?Base $model = null)
    {
        parent::__construct($resource, $model);
        $this->src      = cdnServe($this->id);
        $this->download = cdnServe($this->id, true);
    }
}
