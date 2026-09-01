<?php

namespace Nails\Cdn\Cdn\Monitor;

use Closure;
use Nails\Auth\Cdn\MetaData\SystemKey\UserImport;
use Nails\Cdn\Constants;
use Nails\Cdn\Exception\CdnException;
use Nails\Cdn\Factory\Monitor\Detail;
use Nails\Cdn\Interfaces\Monitor;
use Nails\Cdn\Resource\CdnObject;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Exception\NailsException;
use Nails\Common\Helper\Model\Select;
use Nails\Common\Helper\Model\Sort;
use Nails\Common\Helper\Model\Where;
use Nails\Common\Model\Base;
use Nails\Common\Resource\Entity;
use Nails\Common\Traits\Model\Nestable;
use Nails\Factory;

abstract class ObjectHasMetaDataKey implements Monitor
{
    abstract public function getLabel(): string;

    abstract protected function getKey(): string;

    // --------------------------------------------------------------------------

    /**
     * @return Detail[]
     * @throws FactoryException
     */
    public function locate(CdnObject $oObject, ?Closure $fnCreateDetail = null): array
    {
        $sKey     = $this->getKey();
        $aMatches = array_filter(
            $oObject->metadata ?? [],
            fn($oItem) => $oItem->key === $sKey
        );

        if (!empty($aMatches)) {

            /** @var Detail $oDetail */
            $oDetail = Factory::factory('MonitorDetail', Constants::MODULE_SLUG, $this);
            $oDetail
                ->setData((object) [
                    'key' => $sKey,
                ]);

            return [$oDetail];
        }

        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * @throws CdnException
     */
    public function delete(Detail $oDetail, CdnObject $oObject): void
    {
        throw new CdnException(
            sprintf(
                <<<EOT
                This item (#%d, %s) cannot be automatically deleted because it contains a restricted metadata key (%s).
                EOT,
                $oObject->id,
                $oObject->file->name->human,
                $this->getKey()
            )
        );
    }

    // --------------------------------------------------------------------------

    /**
     * @throws CdnException
     */
    public function replace(Detail $oDetail, CdnObject $oObject, CdnObject $oReplacement): void
    {
        throw new CdnException(
            sprintf(
                <<<EOT
                This item (#%d, %s) cannot be automatically deleted because it contains a restricted metadata key (%s).
                EOT,
                $oObject->id,
                $oObject->file->name->human,
                $this->getKey()
            )
        );
    }
}
