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

namespace Nails\Api\Cdn;

use Nails\Api\Controller\Base;
use Nails\Factory;

class Manager extends Base
{
    /**
     * Require the user be authenticated to use any endpoint
     */
    const REQUIRE_AUTH = true;

    // --------------------------------------------------------------------------

    /**
     * Returns the URL for a manager
     * @return array
     */
    public function getUrl()
    {
        if (!userHasPermission('admin:cdn:manager:object:browse')) {
            return [
                'status' => 401,
                'error'  => 'You do not have permission to use the Media Manager',
            ];
        }

        $oInput = Factory::service('Input');
        return [
            'data' => site_url(
                'admin/cdn/manager?' .
                http_build_query([
                    'bucket'   => $oInput->get('bucket'),
                    'callback' => $oInput->get('callback'),
                ])
            ),
        ];
    }
}
