<?php

/**
 * This class is a wrapper so the CDN class can be loaded via CodeIgniter
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Library
 * @author      Nails Dev Team
 * @link
 */

class Cdn
{
    private $oCdn;

    /**
     * Construct the class, instanticating the CDN Library
     */
    public function __construct()
    {
        //  @todo: load this using Pimple
        $this->oCdn = new \Nails\Cdn\Library\Cdn();
    }

    /**
     * Route all calls to this class tothe CDN Library
     * @param  string $sMethod The name of the method being called
     * @param  array  $aArgs   An array of arguments passed to the method
     * @return mixed
     */
    function __call($sMethod, $aArgs) {

        return call_user_func_array(array($this->oCdn, $sMethod), $aArgs);
    }
}
