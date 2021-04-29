<?php

/**
 * This file provides some shortcuts for the CDN
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Helper
 * @author      Nails Dev Team
 * @link
 */

use Nails\Cdn\Constants;
use Nails\Cdn\Resource\UrlGenerator;
use Nails\Cdn\Service\Cdn;
use Nails\Common\Service\View;
use Nails\Factory;

if (!function_exists('formatBytes')) {

    /**
     * Formats a filesize given in bytes into a human-friendly string
     *
     * @param int $iBytes     The filesize, in bytes
     * @param int $iPrecision The precision to use
     *
     * @return string
     */
    function formatBytes($iBytes, $iPrecision = 2): string
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        return $oCdn->formatBytes($iBytes, $iPrecision);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('returnBytes')) {

    /**
     * Formats a filesize as bytes (e.g max_upload_size)
     * hat-tip: http://php.net/manual/en/function.ini-get.php#96996
     *
     * @param string $sSize The string to convert to bytes
     *
     * @return int
     */
    function returnBytes($sSize): int
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        return $oCdn->returnBytes($sSize);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('maxUploadSize')) {

    /**
     * Returns the configured maximum upload size for this system by inspecting
     * upload_max_filesize and post_max_size, if available.
     *
     * @param bool            $bFormat Whether to format the string using formatBytes
     * @param int|string|null $mBucket Whether to factor a bucket's max upload size into the equation
     *
     * @return int|string
     */
    function maxUploadSize($bFormat = true, $mBucket = null)
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        return $oCdn->maxUploadSize($bFormat, $mBucket);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnObject')) {

    /**
     * Returns a CDN object
     *
     * @param int $iObjectId The ID of the object to get
     *
     * @return stdClass
     */
    function cdnObject($iObjectId)
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        return $oCdn->getObject($iObjectId);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnBucket')) {

    /**
     * Returns a CDN object
     *
     * @param int $iBucketId The ID of the bucket to get
     *
     * @return stdClass
     */
    function cdnBucket($iBucketId)
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        return $oCdn->getBucket($iBucketId);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnServe')) {

    /**
     * Returns the URL for serving raw content from the CDN
     *
     * @param int  $iObjectId      The ID of the object to serve
     * @param bool $bForceDownload Whether or not the URL should stream to the browser, or forcibly download
     *
     * @return UrlGenerator\Crop|null
     */
    function cdnServe($iObjectId, $bForceDownload = false): ?UrlGenerator\Serve
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        return $oCdn->urlServe($iObjectId, $bForceDownload);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnServeRaw')) {

    /**
     * Returns the URL for serving raw content from the CDN driver's source and not running it through the main CDN
     *
     * @param int $iObjectId The ID of the object to serve
     *
     * @return string
     */
    function cdnServeRaw($iObjectId)
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        return $oCdn->urlServeRaw($iObjectId);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnServeZipped')) {

    /**
     * Returns the URL for serving zipped objects
     *
     * @param array  $aObjectIds An array of object ID's to zip together
     * @param string $sFilename  The filename to give the zip file
     *
     * @return string
     */
    function cdnServeZipped($aObjectIds, $sFilename = 'download.zip')
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        return $oCdn->urlServeZipped($aObjectIds, $sFilename);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnCrop')) {

    /**
     * Returns the URL for a crop of an object
     *
     * @param int $iObjectId The Object's ID
     * @param int $iWidth    The width of the thumbnail
     * @param int $iHeight   The height of the thumbnail
     *
     * @return UrlGenerator\Crop|null
     */
    function cdnCrop($iObjectId, $iWidth, $iHeight): ?UrlGenerator\Crop
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        return $oCdn->urlCrop($iObjectId, $iWidth, $iHeight);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnScale')) {

    /**
     * Returns the URL for a scaled thumbnail of an object
     *
     * @param int $iObjectId The Object's ID
     * @param int $iWidth    The width of the thumbnail
     * @param int $iHeight   The height of the thumbnail
     *
     * @return string
     */
    function cdnScale($iObjectId, $iWidth, $iHeight): ?UrlGenerator\Scale
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        return $oCdn->urlScale($iObjectId, $iWidth, $iHeight);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnPlaceholder')) {

    /**
     * Returns the URL for a placeholder graphic
     *
     * @param int $iWidth  The width of the placeholder
     * @param int $iHeight The height of the placeholder
     * @param int $iBorder The width of the border, if any
     *
     * @return string
     */
    function cdnPlaceholder($iWidth, $iHeight, $iBorder = 0)
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        return $oCdn->urlPlaceholder($iWidth, $iHeight, $iBorder);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnBlankAvatar')) {

    /**
     * Returns the URL for a blank avatar graphic
     *
     * @param int        $iWidth  The width of the placeholder
     * @param int        $iHeight The height of the placeholder
     * @param string|int $mSex    The gender of the avatar
     *
     * @return string
     */
    function cdnBlankAvatar($iWidth, $iHeight, $mSex = '')
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        return $oCdn->urlBlankAvatar($iWidth, $iHeight, $mSex);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnAvatar')) {

    /**
     * Returns the URL for a user's avatar
     *
     * @param int $iUserId The user ID to use
     * @param int $iWidth  The width of the avatar
     * @param int $iHeight The height of the avatar
     *
     * @return string
     */
    function cdnAvatar($iUserId = null, $iWidth = 100, $iHeight = 100)
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        return $oCdn->urlAvatar($iUserId, $iWidth, $iHeight);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnExpiringUrl')) {

    /**
     * Returns an expiring URL
     *
     * @param int  $iObject        The ID of the object to server
     * @param int  $iExpires       The length of time the URL should be valid for, in seconds
     * @param bool $bForceDownload Whether or not the URL should stream to the browser, or forcibly download
     *
     * @return string
     */
    function cdnExpiringUrl($iObject, $iExpires, $bForceDownload = false)
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        return $oCdn->urlExpiring($iObject, $iExpires, $bForceDownload);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('getExtFromMime')) {

    /**
     * Get the extension of a file from it's mime
     *
     * @param string $sMime The mime to look up
     *
     * @return string
     */
    function getExtFromMime($sMime)
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        return $oCdn->getExtFromMime($sMime);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('getMimeFromExt')) {

    /**
     * Get the mime of a file from it's extension
     *
     * @param string $sExt The extension to look up
     *
     * @return string
     */
    function getMimeFromExt($sExt)
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        return $oCdn->getMimeFromExt($sExt);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('getMimeFromFile')) {

    /**
     * Get the mime from a file on disk
     *
     * @param string $sFile The file to look up
     *
     * @return string
     */
    function getMimeFromFile($sFile)
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        return $oCdn->getMimeFromFile($sFile);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('cdnObjectPicker')) {

    /**
     * Returns the markup required for cdn Object Pickers
     *
     * @param string $sKey       The name to give the input
     * @param string $sBucket    The bucket we're picking from
     * @param int    $iObjectId  The object which has previously been chosen
     * @param string $sAttr      Any attributes to add to the containing element
     * @param string $sInputAttr Any attributes to add to the input element
     * @param bool   $bReadOnly  Whether picker is readonly
     *
     * @return string
     */
    function cdnObjectPicker($sKey, $sBucket, $iObjectId = null, $sAttr = '', $sInputAttr = '', $bReadOnly = false)
    {
        if ($bReadOnly) {
            $sAttr      .= ' data-readonly="true"';
            $sInputAttr .= ' readonly';
        }
        /** @var View $oView */
        $oView = Factory::service('View');
        return $oView->load(
            'cdn/picker',
            [
                'sKey'       => $sKey,
                'sBucket'    => $sBucket,
                'iObjectId'  => $iObjectId,
                'sAttr'      => $sAttr,
                'sInputAttr' => $sInputAttr,
                'bReadOnly'  => $bReadOnly,
            ],
            true
        );
    }
}
