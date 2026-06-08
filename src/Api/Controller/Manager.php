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
use Nails\Cdn\Admin\Permission;
use Nails\Common\Service\HttpCodes;
use Nails\Common\Service\Input;
use Nails\Factory;

/**
 * Class Manager
 *
 * @package Nails\Cdn\Api\Controller
 */
class Manager extends Api\Controller\Base
{
    /**
     * Require the user be authenticated to use any endpoint
     */
    const REQUIRE_AUTH = true;

    // --------------------------------------------------------------------------

    /**
     * Returns the URL for a manager
     */
    public function getUrl(): Api\Factory\ApiResponse
    {
        if (!userHasPermission(Permission\Object\Browse::class)) {
            /** @var HttpCodes $oHttpCodes */
            $oHttpCodes = Factory::service('HttpCodes');
            throw new Api\Exception\ApiException(
                'You do not have permission to access this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');

        $aQuery = $oInput->get();
        if ($oInput->get('bucket')) {
            $aQuery['bucket'] = $oInput->get('bucket');
        }
        if ($oInput->get('callback')) {
            $aQuery['callback'] = $oInput->get('callback');
        }

        return Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG)
            ->setData(siteUrl(
                \Nails\Cdn\Admin\Controller\Manager::url() . '?' .
                http_build_query($aQuery)
            ));
    }
}
