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

class Manager extends \Nails\Api\Controller\Base
{
    private $oCdn;

    // --------------------------------------------------------------------------

     public static $requiresAuthentication = true;

    // --------------------------------------------------------------------------

    /**
     * Construct the controller
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
        $sBucket   = $this->input->get('bucket');
        $aCallback = $this->input->get('callback');
        $mPassback = $this->input->get('passback');
        $bSecure   = $this->input->get('secure');


        return array('data' => cdnManagerUrl($sBucket, $aCallback, $mPassback, $bSecure));
    }
}
