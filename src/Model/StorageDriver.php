<?php

/**
 * This model manages the CDN storage drivers
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Model
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Cdn\Model;

use Nails\Common\Model\BaseDriver;

class StorageDriver extends BaseDriver
{
    protected $sModule         = 'nailsapp/module-cdn';
    protected $sType           = 'storage';
    protected $bEnableMultiple = false;
}
