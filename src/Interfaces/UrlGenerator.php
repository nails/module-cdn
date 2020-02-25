<?php

namespace Nails\Cdn\Interfaces;

use Nails\Cdn\Resource;
use Nails\Cdn\Service;
use Nails\Cdn\Interfaces;

/**
 * Interface UrlGenerator
 *
 * @package Nails\Cdn\Interfaces
 */
interface UrlGenerator
{
    /**
     * UrlGenerator constructor.
     *
     * @param Service\Cdn          $oCdn      The CDN service
     * @param Service\UrlGenerator $oService  The UrlGenerator service
     * @param int                  $iObjectId The Object to generate the URL for
     */
    public function __construct(Service\Cdn $oCdn, Service\UrlGenerator $oService, int $iObjectId);

    // --------------------------------------------------------------------------

    /**
     * Returns the Object ID
     *
     * @return int
     */
    public function getObjectId(): int;

    // --------------------------------------------------------------------------

    /**
     * Determines whether the URL has been generated yet or not
     *
     * @return bool
     */
    public function isGenerated(): bool;

    // --------------------------------------------------------------------------

    /**
     * Generates the URL
     *
     * @param Resource\CdnObject $oObject The Object Resource
     *
     * @return $this
     */
    public function generate(Resource\CdnObject $oObject): self;

    // --------------------------------------------------------------------------

    /**
     * Calls the appropriate method on the driver
     *
     * @param Interfaces\Driver  $oDriver The driver instance
     * @param Resource\CdnObject $oObject The Object Resource
     *
     * @return string
     */
    public function callDriver(Interfaces\Driver $oDriver, Resource\CdnObject $oObject): string;

    // --------------------------------------------------------------------------

    /**
     * Triggers a URL generation and returns the cURL
     *
     * @return string
     */
    public function __toString();
}
