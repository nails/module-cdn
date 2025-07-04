<?php

namespace Nails\Cdn\Interfaces;

use stdClass;

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
    public function objectCreate(stdClass $oData): bool;
    public function objectExists(string $sFilename, string $sBucket): bool;
    public function objectMove(string $sSourceObject, string $sSourceBucket, string $sTargetObject, string $sTargetBucket);
    public function objectCopy(string $sSourceObject, string $sSourceBucket, string $sTargetObject, string $sTargetBucket);
    public function objectDestroy(string $sObject, string $sBucket): bool;
    public function objectLocalPath(string $sBucket, string $sFilename): bool|string;

    //  Bucket methods
    public function bucketCreate(string $sBucket): bool;
    public function bucketDestroy(string $sBucket): bool;

    //  URL methods
    public function urlServe(string $sObject, string $sBucket, bool $bForceDownload = false): string;
    public function urlServeRaw(string $sObject, string $sBucket): string;
    public function urlServeScheme(bool $bForceDownload = false): string;
    public function urlServeZipped(string $sObjectIds, string $sHash, string $sFilename): string;
    public function urlServeZippedScheme(): string;
    public function urlCrop(string $sObject, string $sBucket, int $iWidth, int $iHeight): string;
    public function urlCropScheme(): string;
    public function urlScale(string $sObject, string $sBucket, int $iWidth, int $iHeight): string;
    public function urlScaleScheme(): string;
    public function urlPlaceholder(int $iWidth, int $iHeight, int $iBorder = 0): string;
    public function urlPlaceholderScheme(): string;
    public function urlBlankAvatar(int $iWidth, int $iHeight, string $sSex = ''): string;
    public function urlBlankAvatarScheme(): string;
    public function urlExpiring(string $sObject, string $sBucket, int $iExpires, bool $bForceDownload = false): string;
    public function urlExpiringScheme(): string;
}
