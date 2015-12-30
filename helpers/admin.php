<?php

/**
 * This file provides some CDN related helpers for admin
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Helper
 * @author      Nails Dev Team
 * @link
 */

if (!function_exists('cdnObjectPicker')) {

    /**
     * Returns the markup required for cdn Object Pickers
     * @param  string $sKey      The name to give the input
     * @param  string $sBucket   The bucket we're picking from
     * @param  int    $iObjectId The object which has previously been chosen
     * @param  string $sAttr     Any attrbutes to add to the containing element
     * @return string
     */
    function cdnObjectPicker($sKey, $sBucket, $iObjectId = null, $sAttr = null)
    {
        $oCi = get_instance();
        return $oCi->load->view(
            'cdn/admin/picker',
            array('key' => $sKey, 'bucket' => $sBucket, 'object' => $iObjectId, 'attr' => $sAttr),
            true
        );
    }
}
