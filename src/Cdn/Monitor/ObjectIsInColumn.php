<?php

namespace Nails\Cdn\Cdn\Monitor;

use Closure;
use Nails\Cdn\Constants;
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
use Nails\Factory;

abstract class ObjectIsInColumn implements Monitor
{
    abstract public function getModel(): Base;

    abstract protected function getColumn(): string;

    // --------------------------------------------------------------------------

    public function getLabel(): string
    {
        return static::class;
    }

    // --------------------------------------------------------------------------

    /**
     * @return Detail[]
     * @throws FactoryException
     * @throws ModelException
     */
    public function locate(CdnObject $oObject, ?Closure $fnCreateDetail = null): array
    {
        $oModel = $this->getModel();
        if (!$oModel->isDestructiveDelete()) {
            $oModel->includeDeleted();
        }

        $fnCreateDetail = $fnCreateDetail ?? function (Entity $oEntity) use ($oModel): Detail {
            return $this->createDetail($oEntity, $oModel);
        };

        return array_map(
            $fnCreateDetail,
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

    protected function getQueryConditions(CdnObject $oObject): array
    {
        return [
            new Where($this->getColumn(), $oObject->id),
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

    protected function getEntityLabel(Entity $oEntity): string
    {
        return $oEntity->label ?? '<no label>';
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     */
    protected function createDetail(Entity $oEntity, Base $oModel, array $aAdditionalData = []): Detail
    {
        $aDetails = [
            'id'    => $oEntity->id,
            /**
             * Label isn't necessary, but helps humans
             * understand what the ID is referring to
             */
            'label' => $this->getEntityLabel($oEntity),
        ];

        if (!$oModel->isDestructiveDelete()) {
            $aDetails[$oModel->getColumnIsDeleted()] = $oEntity->{$oModel->getColumnIsDeleted()};
        }

        /** @var Detail $oDetail */
        $oDetail = Factory::factory('MonitorDetail', Constants::MODULE_SLUG, $this);
        $oDetail
            ->setData((object) array_merge(
                $aDetails,
                $aAdditionalData
            ))
            ->setActions(
                $this->generateActions($oEntity, $oModel)
            );

        return $oDetail;
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     */
    protected function generateActions(Entity $oEntity, Base $oModel): array
    {
        $aActions = [];

        if (property_exists($oEntity, 'url')) {

            if ($oModel->isDestructiveDelete() || empty($oEntity->{$oModel->getColumnIsDeleted()})) {

                /** @var Detail\Action $oAction */
                $oAction    = Factory::factory('MonitorDetailAction', Constants::MODULE_SLUG);
                $aActions[] = $oAction
                    ->setUrl($oEntity->url)
                    ->setLabel('View')
                    ->setTarget('_blank');
            }
        }

        return $aActions;
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws ModelException
     * @throws NailsException
     */
    public function delete(Detail $oDetail, CdnObject $oObject): void
    {
        $this->setObjectId($oDetail, null);
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws ModelException
     * @throws NailsException
     */
    public function replace(Detail $oDetail, CdnObject $oObject, CdnObject $oReplacement): void
    {
        $this->setObjectId($oDetail, $oReplacement->id);
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws ModelException
     * @throws NailsException
     */
    private function setObjectId(Detail $oDetail, ?int $iReplacementId): void
    {
        $this->updateEntity(
            $this->getEntityFromDetail($oDetail),
            [$this->getColumn() => $iReplacementId]
        );
    }

    // --------------------------------------------------------------------------

    /**
     * @throws ModelException
     * @throws NailsException
     */
    protected function getEntityFromDetail(Detail $oDetail): Entity
    {
        $oModel = $this->getModel();
        if (!$oModel->isDestructiveDelete()) {
            $oModel->includeDeleted();
        }

        /** @var Entity $oEntity */
        $oEntity = $oModel->getById($oDetail->getData()->id);
        if (empty($oEntity)) {
            throw new NailsException(
                'Unable to find entity with ID #' . $oDetail->getData()->id
            );
        }

        return $oEntity;
    }

    // --------------------------------------------------------------------------

    /**
     * @throws ModelException
     * @throws FactoryException
     * @throws NailsException
     */
    protected function updateEntity(Entity $oEntity, array $aData): void
    {
        if (!$this->getModel()->update($oEntity->id, $aData)) {
            throw new NailsException(
                sprintf(
                    'Failed to set object #%s (monitor: %s) `%s` to `%s`; error: %s',
                    $oEntity->id,
                    $this->getLabel(),
                    $this->getColumn(),
                    json_encode($aData),
                    $this->getModel()->lastError()
                )
            );
        }
    }
}
