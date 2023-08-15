<?php

namespace Nails\Cdn\Cdn\Monitor;

use Nails\Cdn\Constants;
use Nails\Cdn\Factory\Monitor\Detail;
use Nails\Cdn\Resource\CdnObject;
use Nails\Common\Helper\Model\Like;
use Nails\Common\Helper\Model\Where;
use Nails\Common\Resource\Entity;
use Nails\Factory;

abstract class ObjectIsUrlInText extends ObjectIsInColumn
{
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
                    new Like($this->getColumn() , $oObject->bucket->slug . '/' . $oObject->file->name->disk),
                ])
        );
    }

    // --------------------------------------------------------------------------

    public function delete(Detail $oDetail, CdnObject $oObject): void
    {
        dd(__METHOD__, $oDetail);
    }

    // --------------------------------------------------------------------------

    public function replace(Detail $oDetail, CdnObject $oObject, CdnObject $oReplacement): void
    {
        dd(__METHOD__, $oObject, $oDetail);
    }
}
