<?php

/**
 * The class provides a summary of the events fired by this module
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Events
 * @author      Nails Dev Team
 */

namespace Nails\Cdn;

use Nails\Common\Event\Listener;
use Nails\Common\Events\Base;

/**
 * Class Events
 *
 * @package Nails\Cdn
 */
class Events extends Base
{
    const CONTROLLER_PRE  = 'CONTROLLER:PRE';
    const CONTROLLER_POST = 'CONTROLLER:POST';
}
