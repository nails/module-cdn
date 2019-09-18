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

use Nails\Api\Controller\Base;
use Nails\Api\Exception\ApiException;
use Nails\Common\Service\HttpCodes;
use Nails\Common\Service\Input;
use Nails\Factory;

/**
 * Class Manager
 *
 * @package Nails\Cdn\Api\Controller
 */
class Manager extends Base
{
    /**
     * Require the user be authenticated to use any endpoint
     */
    const REQUIRE_AUTH = true;

    // --------------------------------------------------------------------------

    /**
     * Returns the URL for a manager
     *
     * @return array
     */
    public function getUrl()
    {
        if (!userHasPermission('admin:cdn:manager:object:browse')) {
            /** @var HttpCodes $oHttpCodes */
            $oHttpCodes = Factory::service('HttpCodes');
            throw new ApiException(
                'You do not have permission to access this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');

        return Factory::factory('ApiResponse', 'nails/module-api')
            ->setData(siteUrl(
                'admin/cdn/manager?' .
                http_build_query([
                    'bucket'   => $oInput->get('bucket'),
                    'callback' => $oInput->get('callback'),
                ])
            ));
    }
}
