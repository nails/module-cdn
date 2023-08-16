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

                    $aDetails[] = $oDetail;
                }
            }
        }

        return $aDetails;
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
     * @throws CdnException
     */
    public function delete(Detail $oDetail, CdnObject $oObject): void
    {
        dd(__FILE__, __LINE__);
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws ModelException
     */
    public function replace(Detail $oDetail, CdnObject $oObject, CdnObject $oReplacement): void
    {
        dd(__FILE__, __LINE__);
    }
}
