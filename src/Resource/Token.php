<?php

namespace Nails\Cdn\Resource;

use Nails\Common\Resource\DateTime;

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
