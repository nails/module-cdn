<?php

namespace Nails\Cdn\Resource\CdnObject;

use Nails\Cdn\Resource\Bucket;
use Nails\Cdn\Resource\CdnObject;
use Nails\Common\Resource\Entity;

/**
 * Class Import
 *
 * @package Nails\Cdn\Resource\CdnObject
 */
class Import extends Entity
{
    /** @var int */
    public $bucket_id;

    /** @var Bucket */
    public $bucket;

    /** @var string */
    public $url;

    /** @var string */
    public $mime;

    /** @var int */
    public $size;

    /** @var string */
    public $status;

    /** @var string */
    public $error;
}
