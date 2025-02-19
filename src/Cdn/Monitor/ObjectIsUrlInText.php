<?php

namespace Nails\Cdn\Cdn\Monitor;

use Nails\Cdn\Exception\CdnException;
use Nails\Cdn\Factory\Monitor\Detail;
use Nails\Cdn\Resource\CdnObject;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Helper\Model\Condition;
use Nails\Common\Helper\Model\Like;
use Nails\Common\Helper\Model\Sort;
use Nails\Common\Helper\Model\Where;
use Nails\Common\Resource\Entity;

abstract class ObjectIsUrlInText extends ObjectIsInColumn
{
    /**
     * @return Detail[]
     * @throws FactoryException
     * @throws ModelException
     */
    public function locate(CdnObject $oObject): array
    {
        $oModel = $this->getModel();
        if (!$oModel->isDestructiveDelete()) {
            $oModel->includeDeleted();
        }

        return array_map(
            function (Entity $oEntity): Detail {
                return $this->createDetail($oEntity, $oModel);
            },
            $oModel
                ->getAll(array_filter(array_merge(
                    $this->getQuerySelect(),
                    $this->getQueryConditions($oObject),
                    $this->getQuerySort(),
                )))
        );
    }

    // --------------------------------------------------------------------------

    /**
     * @return Select[]
     */
    protected function getQuerySelect(): array
    {
        return [];
    }

    // --------------------------------------------------------------------------

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
     * @return Sort[]
     */
    protected function getQuerySort(): array
    {
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
                '[Monitor: "%s"; Entity ID: "%s"] This tool is unable to automatically delete objects which appear ' .
                'as a URL within a body of text. This is due to this tool being unable determine how a URL is being ' .
                'used and simply removing it may cause undesireable layout breakages. Please edit the item in ' .
                'question and manually remove.',
                $this->getLabel(),
                $oDetail->getData()->id,
            )
        );
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws ModelException
     */
    public function replace(Detail $oDetail, CdnObject $oObject, CdnObject $oReplacement): void
    {
        $oEntity = $this
            ->getModel()
            ->getById($oDetail->getData()->id);

        $sText = $oEntity->{$this->getColumn()};

        $sSubject     = $oObject->bucket->slug . '/' . $oObject->file->name->disk;
        $sReplacement = $oReplacement->bucket->slug . '/' . $oReplacement->file->name->disk;

        $this
            ->getModel()
            ->update(
                $oEntity->id,
                [$this->getColumn() => str_replace($sSubject, $sReplacement, $sText)]
            );
    }
}
