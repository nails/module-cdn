<?php

/**
 * Direct to media manager
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    AdminController
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Cdn\Admin\Controller;

use Nails\Admin\Controller\Base;
use Nails\Admin\Factory\Nav;
use Nails\Cdn\Admin\Permission;
use Nails\Cdn\Constants;
use Nails\Cdn\Exception\CdnException;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Service\Input;
use Nails\Factory;

/**
 * Class MediaManager
 *
 * @package Nails\Admin\Cdn
 */
class MediaManager extends Base
{
    public static function announce(): Nav|array|null
    {
        return null;
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws CdnException
     */
    public function index(): void
    {
        if (!userHasPermission(Permission\Object\Browse::class)) {
            unauthorised();
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var \Nails\Cdn\Service\MediaManager $oMediaManager */
        $oMediaManager = Factory::service('MediaManager', Constants::MODULE_SLUG);

        redirect(
            $oMediaManager->getUrl(
                $oInput::get()
            )
        );
    }
}
