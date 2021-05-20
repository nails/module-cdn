<?php

/**
 * Admin API end points: CDN buckets
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Cdn\Api\Controller;

use Nails\Api;
use Nails\Api\Factory\ApiResponse;
use Nails\Cdn\Constants;
use Nails\Common\Service\HttpCodes;
use Nails\Common\Service\Input;
use Nails\Factory;

/**
 * Class Bucket
 *
 * @package Nails\Cdn\Api\Controller
 */
class Bucket extends Api\Controller\CrudController
{
    const CONFIG_MODEL_NAME       = 'Bucket';
    const CONFIG_MODEL_PROVIDER   = Constants::MODULE_SLUG;
    const REQUIRE_AUTH            = true;
    const CONFIG_PER_PAGE         = 50;
    const CONFIG_OBJECTS_PER_PAGE = 25;
    const CONFIG_LOOKUP_DATA      = [
        'expand' => ['objects:count'],
        'where'  => [['is_hidden', false]],
    ];

    // --------------------------------------------------------------------------

    /**
     * Returns a paginated list of a bucket's contents
     *
     * @return array
     */
    public function getList()
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var HttpCodes $oHttpCodes */
        $oHttpCodes = Factory::service('HttpCodes');
        /** @var \Nails\Cdn\Model\CdnObject $oObjectModel */
        $oObjectModel = Factory::model('Object', Constants::MODULE_SLUG);

        if (!userHasPermission('admin:cdn:manager:object:browse')) {
            throw new Api\Exception\ApiException(
                'You do not have permission to access this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        $iBucketId = (int) $oInput->get('bucket_id') ?: null;
        $iPage     = (int) $oInput->get('page') ?: 1;

        if (empty($iBucketId)) {
            throw new Api\Exception\ApiException(
                '`bucket_id` is a required field',
                $oHttpCodes::STATUS_BAD_REQUEST
            );
        }

        $aObjects = $oObjectModel->getAll(
            $iPage,
            static::CONFIG_OBJECTS_PER_PAGE,
            ['where' => [['bucket_id', $iBucketId]]]
        );

        return Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG)
            ->setData(array_map(
                function ($oObj) {
                    $oObj->url->preview = $oObj->is_img ? cdnCrop($oObj->id, 400, 400) : null;
                    return $oObj;
                },
                $aObjects
            ))
            ->setMeta([
                'page'     => $iPage,
                'per_page' => static::CONFIG_OBJECTS_PER_PAGE,
            ]);
    }

    // --------------------------------------------------------------------------

    /**
     * Overridden to check user permissions before creating buckets
     *
     * @return array
     */
    public function create(): ApiResponse
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var HttpCodes $oHttpCodes */
        $oHttpCodes = Factory::service('HttpCodes');

        if (!userHasPermission('admin:cdn:manager:bucket:create')) {
            throw new Api\Exception\ApiException(
                'You do not have permission to create this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        //  @todo (Pablo - 2018-08-16) - Remove once CrudController validates properly itself
        if (!$oInput->post('label')) {
            throw new Api\Exception\ApiException(
                '`label` is a required field',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        return parent::create();
    }

    // --------------------------------------------------------------------------

    protected function formatObject($oObj)
    {
        if ($oObj->max_size) {
            $sMaxSize      = $oObj->max_size;
            $sMaxSizeHuman = formatBytes($oObj->max_size);
        } else {
            $sMaxSize      = maxUploadSize(false);
            $sMaxSizeHuman = maxUploadSize();
        }

        return [
            'id'             => $oObj->id,
            'slug'           => $oObj->slug,
            'label'          => $oObj->label,
            'max_size'       => $sMaxSize,
            'max_size_human' => $sMaxSizeHuman,
            'object_count'   => $oObj->objects,
        ];
    }

    // --------------------------------------------------------------------------

    protected function userCan($sAction, $oItem = null)
    {
        //  @todo (Pablo - 2019-06-11) - Restrict deletes and updates to those with permission
        //  @todo (Pablo - 2019-06-11) - Restrict deletes to empty buckets
    }
}
