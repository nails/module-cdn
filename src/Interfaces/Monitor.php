<?php

namespace Nails\Cdn\Interfaces;

use Nails\Cdn\Factory\Detail\Usage;
use Nails\Cdn\Factory\Monitor\Detail;
use Nails\Cdn\Resource\CdnObject;

/**
 * Interface Template
 *
 * @package Nails\Cdn\Interfaces
 */
interface Monitor
{
    /**
     * Returns an identifier for this monitor
     */
    public function getLabel(): string;

    // --------------------------------------------------------------------------

    /**
     * Returns locations where this item is used
     *
     * @return Detail[]
     */
    public function locate(CdnObject $oObject): array;

    // --------------------------------------------------------------------------

    /**
     * Deletes the item identified by the Detail
     */
    public function delete(Detail $oDetail, CdnObject $oObject): void;

    // --------------------------------------------------------------------------

    /**
     * Replaces the item identified by the Detail with the supplied object
     */
    public function replace(Detail $oDetail, CdnObject $oObject, CdnObject $oReplacement): void;
}
