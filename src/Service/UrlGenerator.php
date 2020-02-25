<?php

namespace Nails\Cdn\Service;

use Nails\Cdn\Constants;
use Nails\Cdn\Model\CdnObject;
use Nails\Cdn\Resource;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Helper\Model\Expand;
use Nails\Common\Traits\Caching;
use Nails\Factory;

/**
 * Class UrlGenerator
 *
 * @package Nails\Cdn\Service
 */
class UrlGenerator
{
    /**
     * The resource names for the various sub-classes
     */
    const RESOURCE_CROP  = 'UrlGeneratorCrop';
    const RESOURCE_SCALE = 'UrlGeneratorScale';
    const RESOURCE_SERVE = 'UrlGeneratorServe';

    // --------------------------------------------------------------------------

    /**
     * The generators which have been created
     *
     * @var Resource\UrlGenerator[]
     */
    protected $aGenerators = [];

    /**
     * The Object resources which have been fetched
     *
     * @var Resource\CdnObject[]
     */
    protected $aCachedObjects = [];

    // --------------------------------------------------------------------------

    /**
     * Returns a new Generator
     *
     * @param string     $sResource The type of generator to build
     * @param object|int $mObject   The object ID, or resource
     *
     * @return Resource\UrlGenerator
     * @throws \InvalidArgumentException
     */
    protected function build(string $sResource, $mObject): Resource\UrlGenerator
    {
        if (is_object($mObject)) {
            $mObject = $mObject->id;
        } elseif (!is_numeric($mObject)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Second argument passed to %s must be an object, or an integer; %s passed',
                    __METHOD__,
                    gettype($mObject)
                )
            );
        }

        if (!is_int($mObject) && is_numeric($mObject)) {
            $mObject = (int) $mObject;
        }

        $aArgs = func_get_args();
        array_shift($aArgs);
        array_shift($aArgs);
        $aArgs = array_merge(
            [
                $sResource,
                Constants::MODULE_SLUG,
                Factory::service('Cdn', Constants::MODULE_SLUG),
                $this,
                $mObject,
            ],
            $aArgs
        );

        /** @var Resource\UrlGenerator $oGenerator */
        $oGenerator = call_user_func_array(
            '\Nails\Factory::resource',
            $aArgs
        );

        $this->aGenerators[] = $oGenerator;
        return $oGenerator;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a new Crop Generator
     *
     * @param object|int $mObject The object ID, or resource
     * @param int        $iWidth  The width of the URL
     * @param int        $iHeight The height of the URL
     *
     * @return Resource\UrlGenerator\Crop
     * @throws \InvalidArgumentException
     */
    public function crop($mObject, int $iWidth, int $iHeight): Resource\UrlGenerator\Crop
    {
        /** @var Resource\UrlGenerator\Crop $oGenerator */
        $oGenerator = $this->build(static::RESOURCE_CROP, $mObject, $iWidth, $iHeight);
        return $oGenerator;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a new Scale Generator
     *
     * @param object|int $mObject The object ID, or resource
     * @param int        $iWidth  The width of the URL
     * @param int        $iHeight The height of the URL
     *
     * @return Resource\UrlGenerator\Crop
     * @throws \InvalidArgumentException
     */
    public function scale($mObject, int $iWidth, int $iHeight): Resource\UrlGenerator\Scale
    {
        /** @var Resource\UrlGenerator\Scale $oGenerator */
        $oGenerator = $this->build(static::RESOURCE_SCALE, $mObject, $iWidth, $iHeight);
        return $oGenerator;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a new Serve Generator
     *
     * @param object|int $mObject        The object ID, or resource
     * @param bool       $bForceDownload Whether to force a download, or not
     *
     * @return Resource\UrlGenerator\Crop
     * @throws \InvalidArgumentException
     */
    public function serve($mObject, bool $bForceDownload = false): Resource\UrlGenerator\Serve
    {
        /** @var Resource\UrlGenerator\Serve $oGenerator */
        $oGenerator = $this->build(static::RESOURCE_SERVE, $mObject, $bForceDownload);
        return $oGenerator;
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws ModelException
     */
    public function generate()
    {
        $aObjectIds = [];
        foreach ($this->aGenerators as $oUrlObject) {
            if (!$oUrlObject->isGenerated()) {
                $aObjectIds[] = $oUrlObject->getObjectId();
            }
        }

        $aObjectIds = array_unique($aObjectIds);
        $aObjectIds = array_filter($aObjectIds);
        $aObjectIds = array_values($aObjectIds);

        //  Only fetch new items from the DB
        $aCachedIds = arrayExtractProperty($this->aCachedObjects, 'id');
        $aNewIds    = array_diff($aObjectIds, $aCachedIds);

        if (!empty($aNewIds)) {
            /** @var CdnObject $oObjectModel */
            $oObjectModel = Factory::model('Object', Constants::MODULE_SLUG);
            foreach ($oObjectModel->getByIds($aObjectIds, [new Expand('bucket')]) as $oObject) {
                $this->aCachedObjects[$oObject->id] = $oObject;
            }
        }

        //  @todo (Pablo - 2020-02-25) - Fetch items from trash if needed

        foreach ($this->aGenerators as $oUrlObject) {
            if (in_array($oUrlObject->getObjectId(), $aObjectIds)) {
                $oUrlObject->generate(
                    $this->aCachedObjects[$oUrlObject->getObjectId()]
                );
            }
        }
    }
}
