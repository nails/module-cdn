<?php

namespace Nails\Cdn\Cdn\Monitor;

use Closure;
use Nails\Cdn\Factory\Monitor\Detail;
use Nails\Cdn\Resource\CdnObject;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Exception\NailsException;
use Nails\Common\Resource\Entity;

abstract class ObjectIsCsvInColumn extends ObjectIsInColumn
{
    /**
     * @return Detail[]
     * @throws FactoryException
     * @throws ModelException
     */
    public function locate(CdnObject $oObject, Closure $fnCreateDetail = null): array
    {
        $oModel   = $this->getModel();
        $aDetails = [];
        parent::locate(
            $oObject,
            function (Entity $oEntity) use (&$aDetails, $oObject, $oModel) {
                $aObjectIds = $this->extractIds($oEntity);
                foreach ($aObjectIds as $iObjectId) {
                    if ($iObjectId === $oObject->id) {
                        $aDetails[] = $this->createDetail($oEntity, $oModel);
                    }
                }
            }
        );

        return $aDetails;
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws ModelException
     * @throws NailsException
     */
    public function delete(Detail $oDetail, CdnObject $oObject): void
    {
        $oEntity  = $this->getEntityFromDetail($oDetail);
        $aFileIds = $this->extractIds($oEntity);

        $aFileIds = array_values(
            array_filter(
                $aFileIds,
                fn(int $iFileId) => $iFileId !== $oObject->id
            )
        );

        $this->updateEntity(
            $oEntity,
            [
                $this->getColumn() => implode(',', $aFileIds),
            ]
        );
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws ModelException
     * @throws NailsException
     */
    public function replace(Detail $oDetail, CdnObject $oObject, CdnObject $oReplacement): void
    {
        $oEntity  = $this->getEntityFromDetail($oDetail);
        $aFileIds = $this->extractIds($oEntity);

        $aFileIds = array_values(
            array_map(
                function (int $iFileId) use ($oObject, $oReplacement) {
                    return $iFileId === $oObject->id
                        ? $oReplacement->id
                        : $iFileId;
                },
                $aFileIds
            )
        );

        $this->updateEntity(
            $oEntity,
            [
                $this->getColumn() => implode(',', $aFileIds),
            ]
        );
    }

    // --------------------------------------------------------------------------

    /**
     * @return int[]
     */
    protected function extractIds(Entity $oEntity): array
    {
        return array_map(
            'intval',
            array_map(
                'trim',
                explode(
                    ',',
                    $oEntity->{$this->getColumn()}
                )
            )
        );
    }
}
