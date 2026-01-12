<?php

/**
 * Admin API end points: CDN Manager
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Cdn\Api\Controller;

use Nails\Api;
use Nails\Cdn\Constants;
use Nails\Cdn\Exception\CdnException;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Service\HttpCodes;
use Nails\Common\Service\Input;
use Nails\Factory;

/**
 * Class Manager
 *
 * @package Nails\Cdn\Api\Controller
 */
class MediaManager extends Api\Controller\Base
{
    /**
     * Require the user be authenticated to use any endpoint
     */
    const REQUIRE_AUTH = true;

    // --------------------------------------------------------------------------

    /**
     * Returns the URL for a manager
     *
     * @throws Api\Exception\ApiException
     * @throws FactoryException
     * @throws ModelException
     * @throws CdnException
     */
    public function getUrl(): Api\Factory\ApiResponse
    {
        if (!userHasPermission('admin:cdn:mediamanager:object:browse')) {
            /** @var HttpCodes $oHttpCodes */
            $oHttpCodes = Factory::service('HttpCodes');
            throw new Api\Exception\ApiException(
                'You do not have permission to access this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var \Nails\Cdn\Service\MediaManager $oMediaManager */
        $oMediaManager = Factory::service('MediaManager', Constants::MODULE_SLUG);
        /** @var \Nails\Cdn\Model\Bucket $oBucketModel */
        $oBucketModel = Factory::model('Bucket', Constants::MODULE_SLUG);
        /** @var \Nails\Cdn\Model\CdnObject $oObjectModel */
        $oObjectModel = Factory::model('Object', Constants::MODULE_SLUG);

        if ($oInput->get('bucket')) {
            /** @var \Nails\Cdn\Resource\Bucket|null $oBucket */
            $oBucket = $oBucketModel->getByIdOrSlug($oInput->get('bucket'));
        }

        if ($oInput->get('object')) {
            /** @var \Nails\Cdn\Resource\CdnObject|null $oObject */
            $oObject = $oObjectModel->getById($oInput->get('object'));
        }

        return Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG)
            ->setData($oMediaManager
                ->getUrl(
                    query: [
                        'bucket'      => $oInput->get('bucket'),
                        'bucket_id'   => $oBucket->id ?? null,
                        'bucket_slug' => $oBucket->slug ?? null,
                        'object_id'   => $oObject->id ?? null,
                        'callback'    => $oInput->get('callback'),
                    ]
                ));
    }
}
