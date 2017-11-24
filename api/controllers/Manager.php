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

use Nails\Factory;
use Nails\Api\Controller\Base;

class Manager extends Base
{
    /**
     * Require the user be authenticated to use any endpoint
     */
    const REQUIRE_AUTH = true;

    // --------------------------------------------------------------------------

    private $oCdn;

    // --------------------------------------------------------------------------

    /**
     * Manager constructor.
     *
     * @param $apiRouter
     */
    public function __construct($apiRouter)
    {
        parent::__construct($apiRouter);
        $this->oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the URL for a manager
     * @return array
     */
    public function getUrl()
    {
        $oInput = Factory::service('Input');
        return [
            'data' => site_url(
                'admin/cdn/manager?' .
                http_build_query([
                    'bucket'   => $oInput->get('bucket'),
                    'callback' => $oInput->get('callback'),
                    'passback' => $oInput->get('passback'),
                    'secure'   => $oInput->get('secure'),
                ])
            ),
        ];
    }
}
