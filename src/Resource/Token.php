<?php

namespace Nails\Cdn\Resource;

use Nails\Common\Resource\DateTime;
use Nails\Common\Resource\Entity;

/**
 * Class Token
 *
 * @package Nails\Cdn\Resource
 */
class Token extends Entity
{
    /**
     * The token
     *
     * @var string
     */
    public $token;

    /**
     * The date the token expires
     *
     * @var DateTime
     */
    public $expires;
}
