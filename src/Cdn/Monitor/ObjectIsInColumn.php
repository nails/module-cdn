<?php

namespace Nails\Cdn\Cdn\Monitor;

use Nails\Cdn\Constants;
use Nails\Cdn\Factory\Monitor\Detail;
use Nails\Cdn\Interfaces\Monitor;
use Nails\Cdn\Resource\CdnObject;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Exception\NailsException;
use Nails\Common\Helper\Model\Where;
use Nails\Common\Helper\Strings;
use Nails\Common\Model\Base;
use Nails\Common\Resource\Entity;
use Nails\Factory;

abstract class ObjectIsInColumn implements Monitor
{
    abstract protected function getModel(): Base;

    abstract protected function getColumn(): string;

    // --------------------------------------------------------------------------

    public function getLabel(): string
    {
        return static::class;
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws ModelException
     */
    public function locate(CdnObject $oObject): array
    {
        return array_map(
            function (Entity $oEntity): Detail {
                /** @var Detail $oDetail */
                $oDetail = Factory::factory('MonitorDetail', Constants::MODULE_SLUG, $this);
                $oDetail->setData((object) [
                    'id'    => $oEntity->id,
                    /**
                     * Label isn't necessary, but helps humans
                     * understand what the ID is referring to
                     */
                    'label' => $oEntity->label ?? '<no label>',
                ]);
                return $oDetail;
            },
            $this
                ->getModel()
                ->getAll([
                    new Where($this->getColumn(), $oObject->id),
                ])
        );
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws ModelException
     * @throws NailsException
     */
    public function delete(Detail $oDetail, CdnObject $oObject): void
    {
        $this->setObjectId($oDetail->getData()->id, null);
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws ModelException
     * @throws NailsException
     */
    public function replace(Detail $oDetail, CdnObject $oObject, CdnObject $oReplacement): void
    {
        $this->setObjectId($oDetail->getData()->id, $oReplacement->id);
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws ModelException
     * @throws NailsException
     */
    private function setObjectId(int $iId, ?int $iReplacementId): void
    {
        if (!$this->getModel()->update($iId, [$this->getColumn() => $iReplacementId])) {
            throw new NailsException(
                sprintf(
                    'Failed to set object #%s (monitor: %s) `%s` to %s; error: %s',
                    $iId,
                    $this->getLabel(),
                    $this->getColumn(),
                    $iReplacementId ?? 'NULL',
                    $this->getModel()->lastError()
                )
            );
        }
    }
}
