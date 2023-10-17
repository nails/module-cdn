<?php

namespace Nails\Cdn\Cdn\Monitor;

use Nails\Cdn\Constants;
use Nails\Cdn\Exception\CdnException;
use Nails\Cdn\Factory\Monitor\Detail;
use Nails\Cdn\Resource\CdnObject;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Helper\Model\Like;
use Nails\Common\Helper\Model\Where;
use Nails\Common\Resource\Entity;
use Nails\Factory;

abstract class ObjectIsCsvInColumn extends ObjectIsInColumn
{
    /**
     * @return Detail[]
     * @throws FactoryException
     * @throws ModelException
     */
    public function locate(CdnObject $oObject): array
    {
        /** @var Entity[] $aResults */
        $aResults = $this
            ->getModel()
            ->getAll([
                new Like($this->getColumn(), $oObject->id),
            ]);

        $aDetails = [];
        foreach ($aResults as $oEntity) {

            $aObjectIds = $this->extractIds($oEntity);

            foreach ($aObjectIds as $iObjectId) {
                if ($iObjectId === $oObject->id) {
                    $aDetails[] = $this->createDetail($oEntity);
                }
            }
        }

        return $aDetails;
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws ModelException
     */
    public function delete(Detail $oDetail, CdnObject $oObject): void
    {
        $iId    = $oDetail->getData()->id;
        $aFiles = $this->extractIdsFromEntityId($iId);

        $aFiles = array_values(
            array_filter(
                $aFiles,
                fn(int $iFileId) => $iFileId !== $oObject->id
            )
        );

        $this->updateEntity($iId, $aFiles);
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws ModelException
     */
    public function replace(Detail $oDetail, CdnObject $oObject, CdnObject $oReplacement): void
    {
        $iId    = $oDetail->getData()->id;
        $aFiles = $this->extractIdsFromEntityId($oDetail->getData()->id);

        $aFiles = array_values(
            array_map(
                function (int $iFileId) use ($oObject, $oReplacement) {
                    return $iFileId === $oObject->id
                        ? $oReplacement->id
                        : $iFileId;
                },
                $aFiles
            )
        );

        $this->updateEntity($iId, $aFiles);
    }

    // --------------------------------------------------------------------------

    /**
     * @return int[]
     */
    protected function extractIds(Entity $oEntity): array
    {
        return array_map('intval', array_map('trim', explode(',', $oEntity->{$this->getColumn()})));
    }

    // --------------------------------------------------------------------------

    /**
     * @throws ModelException
     */
    protected function extractIdsFromEntityId(int $iId): array
    {
        $oEntity = $this
            ->getModel()
            ->getById($iId);

        return $this->extractIds($oEntity);
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws ModelException
     */
    protected function updateEntity(int $iEntityId, array $aFileIds): void
    {
        $this
            ->getModel()
            ->update(
                $iEntityId,
                [
                    $this->getColumn() => implode(',', $aFileIds),
                ]
            );
    }
}
