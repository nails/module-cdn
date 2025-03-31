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
use Nails\Common\Service\HttpCodes;
use Nails\Common\Service\Input;
use Nails\Common\Service\Session;
use Nails\Factory;

/**
 * Class Manager
 *
 * @package Nails\Cdn\Api\Controller
 */
class Manager extends Api\Controller\Base
{
    /**
     * Require the user be authenticated to use any endpoint
     */
    const REQUIRE_AUTH = true;

    // --------------------------------------------------------------------------

    /**
     * Returns the URL for a manager
     *
     * @return array
     */
    public function getUrl()
    {
        if (!userHasPermission('admin:cdn:manager:object:browse')) {
            /** @var HttpCodes $oHttpCodes */
            $oHttpCodes = Factory::service('HttpCodes');
            throw new Api\Exception\ApiException(
                'You do not have permission to access this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Session $oSession */
        $oSession = Factory::service('Session');
        /** @var \Nails\Cdn\Model\Bucket $oBucketModel */
        $oBucketModel = Factory::model('Bucket', Constants::MODULE_SLUG);

        if ($oInput->get('bucket')) {
            /** @var \Nails\Cdn\Resource\Bucket|null $oBucket */
            $oBucket = $oBucketModel->getByIdOrSlug($oInput->get('bucket'));
        }

        $sBaseUrl = $oSession->getUserData('MEDIA_MANAGER_DEFAULT') === 2
            ? 'admin/cdn/mediaManagerV2'
            : 'admin/cdn/manager';

        return Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG)
            ->setData(siteUrl(
                $sBaseUrl . '?' .
                http_build_query([
                    'bucket'      => $oInput->get('bucket'),
                    'bucket_id'   => $oBucket->id ?? null,
                    'bucket_slug' => $oBucket->slug ?? null,
                    'callback'    => $oInput->get('callback'),
                ])
            ));
    }
}
