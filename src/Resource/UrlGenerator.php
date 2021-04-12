<?php

namespace Nails\Cdn\Resource;

use Nails\Cdn\Constants;
use Nails\Cdn\Interfaces;
use Nails\Cdn\Service;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Resource;
use Nails\Factory;

/**
 * Class UrlGenerator
 *
 * @package Nails\Cdn\Resource
 */
abstract class UrlGenerator extends Resource implements Interfaces\UrlGenerator, \JsonSerializable
{
    /**
     * The CDN service
     *
     * @var Service\Cdn
     */
    protected $oCdn;

    /**
     * The URL Generator service
     *
     * @var Service\UrlGenerator
     */
    protected $oService;

    /**
     * The Object ID
     *
     * @var int
     */
    protected $iObjectId;

    /**
     * The generated URL
     *
     * @var string
     */
    protected $sUrl;

    // --------------------------------------------------------------------------

    /**
     * UrlGenerator constructor.
     *
     * @param Service\Cdn          $oCdn      The Cdn service
     * @param Service\UrlGenerator $oService  The UrlGenerator service
     * @param int                  $iObjectId The Object to generate the URL for
     */
    public function __construct(Service\Cdn $oCdn, Service\UrlGenerator $oService, int $iObjectId)
    {
        $this->oCdn      = $oCdn;
        $this->oService  = $oService;
        $this->iObjectId = $iObjectId;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the Object ID
     *
     * @return int
     */
    public function getObjectId(): int
    {
        return $this->iObjectId;
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the URL has been generated or not
     *
     * @return bool
     */
    public function isGenerated(): bool
    {
        return !is_null($this->sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the URL
     *
     * @param CdnObject $oObject The Object Resource
     *
     * @return Interfaces\UrlGenerator
     * @throws FactoryException
     */
    public function generate(CdnObject $oObject): Interfaces\UrlGenerator
    {
        /** @var Service\StorageDriver $oStorageDriver */
        $oStorageDriver = Factory::service('StorageDriver', Constants::MODULE_SLUG);
        $oDriver        = $oStorageDriver->getInstance($oObject->driver);

        $this->sUrl = siteUrl($this->callDriver($oDriver, $oObject));

        if ($oObject instanceof CdnObject\Trash) {
            $this->sUrl .= (strpos('?', $this->sUrl) !== false ? '&' : '?') . 'trashed=1';
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Generates and returns the URL
     *
     * @return string
     * @throws FactoryException
     * @throws ModelException
     */
    public function __toString()
    {
        if (!$this->isGenerated()) {
            $this->oService->generate();
        }

        return $this->sUrl ?? '';
    }

    // --------------------------------------------------------------------------

    /**
     * When serialised, return the URL ratehr than serialize the object
     *
     * @return string
     * @throws FactoryException
     * @throws ModelException
     */
    public function jsonSerialize()
    {
        return $this->__toString();
    }
}
