<?php

namespace Nails\Cdn\Cdn\Monitor;

use Nails\Cdn\Exception\CdnException;
use Nails\Cdn\Factory\Monitor\Detail;
use Nails\Cdn\Resource\CdnObject;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Exception\NailsException;
use Nails\Common\Helper\Model\Condition;
use Nails\Common\Helper\Model\Like;
use Nails\Common\Helper\Model\Where;
use Nails\Common\Resource\Entity;

abstract class ObjectIsUrlInText extends ObjectIsInColumn
{
    /**
     * @return Where[]|Condition[]
     */
    protected function getQueryConditions(CdnObject $oObject): array
    {
        return [
            new Like($this->getColumn(), $oObject->bucket->slug . '/' . $oObject->file->name->disk),
        ];
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
                [Monitor: "%s"; Entity ID: "%s"] This tool is unable to automatically delete objects which appear
                as a URL within a body of text. This is due to this tool being unable determine how a URL is being
                used and simply removing it may cause undesirable layout breakages. Please edit the item in
                question and manually remove.
                EOT,
                $this->getLabel(),
                $oDetail->getData()->id,
            )
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
        $oEntity = $this->getEntityFromDetail($oDetail);

        d(static::class);
        $sText = $oEntity->{$this->getColumn()};

        $sSubject     = $oObject->bucket->slug . '/' . $oObject->file->name->disk;
        $sReplacement = $oReplacement->bucket->slug . '/' . $oReplacement->file->name->disk;

        $this->updateEntity(
            $oEntity,
            [
                $this->getColumn() => str_replace($sSubject, $sReplacement, $sText),
            ]
        );
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws ModelException
     * @throws NailsException
     */
    protected function updateEntity(Entity $oEntity, array $aData): void
    {
        if (!$this->getModel()->update($oEntity->id, $aData)) {
            throw new NailsException(
                sprintf(
                    'Failed to set object #%s (monitor: %s) `%s`; error: %s',
                    $oEntity->id,
                    $this->getLabel(),
                    $this->getColumn(),
                    $this->getModel()->lastError()
                )
            );
        }
    }
}
