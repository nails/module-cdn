<?php

namespace Nails\Cdn\Interfaces;

/**
 * Interface Driver
 *
 * @package Nails\Cdn\Interfaces
 */
interface Driver
{
    //  Error properties & methods
    public function lastError();

    //  Object methods
    public function objectCreate($oData);
    public function objectExists($sFilename, $sBucket);
    public function objectDestroy($sObject, $sBucket);
    public function objectLocalPath($sBucket, $sFilename);

    //  Bucket methods
    public function bucketCreate($sBucket);
    public function bucketDestroy($sBucket);

    //  URL methods
    public function urlServe($sObject, $sBucket, $bForceDownload = false);
    public function urlServeRaw($sObject, $sBucket);
    public function urlServeScheme($bForceDownload = false);
    public function urlServeZipped($sObjectIds, $sHash, $sFilename);
    public function urlServeZippedScheme();
    public function urlCrop($sObject, $sBucket, $iWidth, $iHeight);
    public function urlCropScheme();
    public function urlScale($sObject, $sBucket, $iWidth, $iHeight);
    public function urlScaleScheme();
    public function urlPlaceholder(int $iWidth, int $iHeight, int $iBorder = 0);
    public function urlPlaceholderScheme();
    public function urlBlankAvatar(int $iWidth, int $iHeight, string $sSex = '');
    public function urlBlankAvatarScheme();
    public function urlExpiring($sObject, $sBucket, $iExpires, $bForceDownload = false);
    public function urlExpiringScheme();
}
