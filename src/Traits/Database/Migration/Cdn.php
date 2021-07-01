<?php

namespace Nails\Cdn\Traits\Database\Migration;

use Nails\Cdn\Constants;
use Nails\Cdn\Exception\CdnException;
use Nails\Cdn\Resource;
use Nails\Common\Exception\FactoryException;
use Nails\Factory;

/**
 * Trait Cdn
 *
 * @package Nails\Cdn\Traits\Database\Migration
 */
trait Cdn
{
    /**
     * Uploads a file into the CDN
     *
     * @param string $sPath   The path of the file to upload
     * @param string $sBucket The bucket to upload into
     *
     * @return int
     * @throws CdnException
     * @throws FactoryException
     */
    protected function uploadFromDisk(string $sPath, string $sBucket): int
    {
        /** @var \Nails\Cdn\Service\Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);

        $oObject = $oCdn->objectCreate($sPath, $sBucket);
        if (empty($oObject)) {
            throw new CdnException(sprintf(
                'Failed to upload file at path %s: %s',
                $sPath,
                $oCdn->lastError()
            ));
        }

        return $oObject->id;
    }
}
