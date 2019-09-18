<?php

/**
 * This model handles interactions with the module's "token" table.
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    model
 * @author      Nails Dev Team <hello@nailsapp.co.uk>
 */

namespace Nails\Cdn\Model;

use Nails\Common\Model\Base;

/**
 * Class Token
 *
 * @package Nails\Cdn\Model
 */
class Token extends Base
{
    const TABLE             = NAILS_DB_PREFIX . 'cdn_token';
    const RESOURCE_NAME     = 'Token';
    const RESOURCE_PROVIDER = 'nails/module-cdn';
    const AUTO_SET_TOKEN    = true;
}
