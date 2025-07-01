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

    const OBJECT_CREATE  = 'OBJECT:CREATE';
    const OBJECT_CREATED = 'OBJECT:CREATED';

    const OBJECT_DELETE  = 'OBJECT:DELETE';
    const OBJECT_DELETED = 'OBJECT:DELETED';

    const OBJECT_RESTORE  = 'OBJECT:RESTORE';
    const OBJECT_RESTORED = 'OBJECT:RESTORED';

    const OBJECT_DESTROY   = 'OBJECT:DESTROY';
    const OBJECT_DESTROYED = 'OBJECT:DESTROYED';

    const OBJECT_COPY   = 'OBJECT:COPY';
    const OBJECT_COPIED = 'OBJECT:COPIED';

    const OBJECT_MOVE  = 'OBJECT:MOVE';
    const OBJECT_MOVED = 'OBJECT:MOVED';

    const OBJECT_REPLACE  = 'OBJECT:REPLACE';
    const OBJECT_REPLACED = 'OBJECT:REPLACED';

    const BUCKET_CREATE  = 'BUCKET:CREATE';
    const BUCKET_CREATED = 'BUCKET:CREATED';

    const BUCKET_DESTROY   = 'BUCKET:DESTROY';
    const BUCKET_DESTROYED = 'BUCKET:DESTROYED';

}
