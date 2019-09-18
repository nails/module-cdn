<?php

/**
 * This class provides some common CDN controller functionality in admin
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Cdn\Controller;

use Nails\Cdn\Constants;
use Nails\Factory;
use Nails\Admin\Controller\Base;

class BaseAdmin extends Base
{
    public function __construct()
    {
        parent::__construct();
        $oAsset = Factory::service('Asset');
        $oAsset->load('admin.min.css', Constants::MODULE_SLUG);
    }
}
