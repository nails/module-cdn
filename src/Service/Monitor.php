<?php

/**
 * This service monitors CDN Templates
 *
 * @package    Nails
 * @subpackage module-cdn
 * @category   Service
 * @author     Nails Dev Team
 */

namespace Nails\Cdn\Service;

use Nails\Cdn\Console\Command\Monitor\Unused;
use Nails\Cdn\Constants;
use Nails\Cdn\Exception\NotFoundException;
use Nails\Cdn\Factory\Monitor\Detail;
use Nails\Cdn\Resource\CdnObject;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\NailsException;
use Nails\Components;
use Nails\Cdn\Interfaces;
use Nails\Factory;
use Symfony\Component\Console\Helper\ProgressIndicator;

/**
 * Class Monitor
 *
 * @package Nails\Cdn\Service
 */
class Monitor
{
    /** @var Interfaces\Monitor[] */
    protected $aMappers;

    // --------------------------------------------------------------------------

    /**
     * Template constructor.
     *
     * @throws NailsException
     */
    public function __construct()
    {
        $this->aMappers = $this->discoverMappers();
    }

    // --------------------------------------------------------------------------

    /**
     * Discovers Template mappers
     *
     * @return Interfaces\Monitor[]
     * @throws NailsException
     */
    protected function discoverMappers(): array
    {
        $aClasses = [];

        foreach (Components::available() as $oComponent) {

            $oClasses = $oComponent
                ->findClasses('Cdn\\Monitor')
                ->whichImplement(Interfaces\Monitor::class)
                ->whichCanBeInstantiated();

            foreach ($oClasses as $sClass) {
                $aClasses[] = new $sClass();
            }
        }

        return $aClasses;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns metadata keys that are managed by the system and should be treated as read-only.
     *
     * @return Interfaces\MetaData\SystemKey[]
     */
    public function getSystemMetadataKeys(): array
    {
        $aKeys = [];

        foreach (Components::available() as $oComponent) {

            $oClasses = $oComponent
                ->findClasses('Cdn\\MetaData\\SystemKey')
                ->whichImplement(Interfaces\MetaData\SystemKey::class)
                ->whichCanBeInstantiated();

            foreach ($oClasses as $sClass) {
                $aKeys[] = new $sClass();
            }
        }

        return array_values(
            array_filter(
                array_unique(
                    array_map(
                        fn($oKey) => $oKey->get(),
                        $aKeys
                    )
                )
            )
        );
    }

    // --------------------------------------------------------------------------

    /**
     * @return Detail[]
     */
    public function locate(CdnObject $oObject, ?ProgressIndicator $oProgressIndicator = null): array
    {
        $aLocations = [];
        foreach ($this->aMappers as $oMapper) {
            $aLocations = array_merge($aLocations, $oMapper->locate($oObject));
            if ($oProgressIndicator) {
                $oProgressIndicator->advance();
            }
        }
        return $aLocations;
    }
}
