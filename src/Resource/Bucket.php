<?php

namespace Nails\Cdn\Resource;

use Nails\Common\Resource\Entity;

/**
 * Class Bucket
 *
 * @package Nails\Cdn\Resource
 */
class Bucket extends Entity
{
    /** @var string */
    public $slug;

    /** @var string */
    public $label;

    /** @var string */
    public $allowed_types;

    /** @var int */
    public $max_size;

    /** @var int */
    public $disk_quota;

    /** @var bool */
    public $is_hidden;
}
