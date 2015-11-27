<?php

use Nails\Factory;

if (!function_exists('cdnServe')) {

    /**
     * Returns the URL for serving raw content from the CDN
     * @param  integer $iObjectId      The ID of the object to serve
     * @param  boolean $bForceDownload Whether or not the URL should stream to the browser, or forcibly download
     * @return string
     */
    function cdnServe($iObjectId, $bForceDownload = false)
    {
        $oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');
        return $oCdn->urlServe($iObjectId, $bForceDownload);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnServeZipped')) {

    /**
     * Returns the URL for serving zipped objects
     * @param  array  $aObjectIds An array of object ID's to zip together
     * @param  string $sFilename  The filename to give the zip file
     * @return string
     */
    function cdnServeZipped($aObjectIds, $sFilename = 'download.zip')
    {
        $oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');
        return $oCdn->urlServeZipped($aObjectIds, $sFilename);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnCrop')) {

    /**
     * Returns the URL for a crop of an object
     * @param  integer $iObjectId The Object's ID
     * @param  integer $iWidth    The width of the thumbnail
     * @param  integer $iHeight   The height of the thumbnail
     * @return string
     */
    function cdnCrop($iObjectId, $iWidth, $iHeight)
    {
        $oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');
        return $oCdn->urlCrop($iObjectId, $iWidth, $iHeight);
    }
}


// --------------------------------------------------------------------------

if (!function_exists('cdnScale')) {

    /**
     * Returns the URL for a scaled thumbnail of an object
     * @param  integer $iObjectId The Object's ID
     * @param  integer $iWidth    The width of the thumbnail
     * @param  integer $iHeight   The height of the thumbnail
     * @return string
     */
    function cdnScale($iObjectId, $iWidth, $iHeight)
    {
        $oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');
        return $oCdn->urlScale($iObjectId, $iWidth, $iHeight);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnPlaceholder')) {

    /**
     * Returns the URL for a placeholder graphic
     * @param  integer $iWidth  The width of the placeholder
     * @param  integer $iHeight The height of the placeholder
     * @param  integer $iBorder The width of the border, if any
     * @return string
     */
    function cdnPlaceholder($iWidth, $iHeight, $iBorder = 0)
    {
        $oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');
        return $oCdn->urlPlaceholder($iWidth, $iHeight, $iBorder);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnBlankAvatar')) {

    /**
     * Returns the URL for a blank avatar graphic
     * @param  integer        $iWidth  The width of the placeholder
     * @param  integer        $iHeight The height of the placeholder
     * @param  string|integer $mSex    The gender of the avatar
     * @return string
     */
    function cdnBlankAvatar($iWidth, $iHeight, $mSex = '')
    {
        $oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');
        return $oCdn->urlBlankAvatar($iWidth, $iHeight, $mSex);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnAvatar')) {

    /**
     * Returns the URL for a user's avatar
     * @param  integer $iUserId The user ID to use
     * @param  integer $iWidth  The width of the avatar
     * @param  integer $iHeight The height of the avatar
     * @return string
     */
    function cdnAvatar($iUserId = null, $iWidth = 100, $iHeight = 100)
    {
        $oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');
        return $oCdn->urlAvatar($iUserId, $iWidth, $iHeight);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnExpiringUrl')) {

    /**
     * Returns an expiring URL
     * @param  integer $iObject        The ID of the object to server
     * @param  integer $expires        The length of time the URL should be valid for, in seconds
     * @param  boolean $bForceDownload Whether or not the URL should stream to the browser, or forcibly download
     * @return string
     */
    function cdnExpiringUrl($iObject, $iExpires, $bForceDownload = false)
    {
        $oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');
        return $oCdn->urlExpiring($iObject, $iExpires, $bForceDownload);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('getExtFromMime')) {

    /**
     * Get the extension of a file from it's mime
     * @param  string $sMime The mime to look up
     * @return string
     */
    function getExtFromMime($sMime)
    {
        $oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');
        return $oCdn->getExtFromMime($sMime);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('getMimeFromExt')) {

    /**
     * Get the mime of a file from it's extension
     * @param  string $sExt The extension to look up
     * @return string
     */
    function getMimeFromExt($sExt)
    {
        $oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');
        return $oCdn->getMimeFromExt($sExt);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('getMimeFromFile')) {

    /**
     * Get the mime from a file on disk
     * @param  string $sFile The file to look up
     * @return string
     */
    function getMimeFromFile($sFile)
    {
        $oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');
        return $oCdn->getMimeFromFile($sFile);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnManagerUrl')) {

    /**
     * Generate a valid URL for the CDN Manager
     * @param  string  $sBucket   The bucket the manager should use
     * @param  array   $aCallback The callback the manager should use for "insert" buttons
     * @param  mixed   $mPassback Any data to pass back to the callback
     * @param  boolean $bSecure   Whether or not the link should be secure
     * @return string
     */
    function cdnManagerUrl($sBucket, $aCallback = array(), $mPassback = null, $bSecure = false)
    {
        $aParams = array();

        /**
         * The callback should be a two element array, the first being the
         * instance variable, the second being the method name.
         */
        $aParams['callback'] = $aCallback;

        /**
         * Passback is any data that the caller wishes to be sent back to the callback
         */

        $aParams['passback'] = json_encode($mPassback);

        /**
         * The bucket should be hashed up and paired with an irreversible hash for
         * verification. Why? So that it's not trivial to mess about with buckets
         * willy nilly.
         */

        $iNonce = time();

        $aParams['bucket'] = get_instance()->encrypt->encode($sBucket . '|' . $iNonce, APP_PRIVATE_KEY);
        $aParams['hash']   = md5($sBucket . '|' . $iNonce . '|' . APP_PRIVATE_KEY);

        //  Prep the query string
        $aParams = http_build_query($aParams);

        return site_url('cdn/manager/browse?' . $aParams, $bSecure);
    }
}
