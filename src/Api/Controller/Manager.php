<?php

/**
 * Admin API end points: CDN Manager
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Cdn\Api\Controller;

use Nails\Api;
use Nails\Cdn\Constants;
use Nails\Cdn\Exception\CdnException;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Service\HttpCodes;
use Nails\Common\Service\Input;
use Nails\Factory;

/**
 * Class Manager
 *
 * @package Nails\Cdn\Api\Controller
 */
class Manager extends MediaManager
{
    //  This class serves purely as a backwards-compatability proxy
}
