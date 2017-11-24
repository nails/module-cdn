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

namespace Nails\Api\Cdn;

use Nails\Api\Controller\DefaultController;
use Nails\Factory;

class Bucket extends DefaultController
{
    const CONFIG_MODEL_NAME           = 'Bucket';
    const CONFIG_MODEL_PROVIDER       = 'nailsapp/module-cdn';
    const REQUIRE_AUTH                = true;
    const CONFIG_MAX_ITEMS_PER_PAGE   = null;
    const CONFIG_MAX_OBJECTS_PER_PAGE = 50;

    // --------------------------------------------------------------------------

    /**
     * Returns a paginated list of a bucket's contents
     * @return array
     */
    public function getList()
    {
        if (!userHasPermission('admin:cdn:manager:object:browse')) {
            return [
                'status' => 401,
                'error'  => 'You do not have permission to list buckets',
            ];
        }

        $oInput       = Factory::service('Input');
        $oObjectModel = Factory::model('Object', 'nailsapp/module-cdn');
        $iBucketId    = (int) $oInput->get('bucket_id') ?: null;
        $iPage        = (int) $oInput->get('page') ?: 1;

        if (empty($iBucketId)) {
            return [
                'status' => 400,
                'error'  => '`bucket_id` is a required field',
            ];
        }

        $aObjects = $oObjectModel->getAll(
            $iPage,
            static::CONFIG_MAX_OBJECTS_PER_PAGE,
            ['where' => [['bucket_id', $iBucketId]]]
        );

        return [
            'page'     => $iPage,
            'per_page' => static::CONFIG_MAX_OBJECTS_PER_PAGE,
            'data'     => array_map(
                function ($oObj) {
                    $oObj->is_img = isset($oObj->img);
                    $oObj->url    = (object) [
                        'src'     => cdnServe($oObj->id),
                        'preview' => isset($oObj->img) ? cdnCrop($oObj->id, 400, 400) : null,
                    ];
                    return $oObj;
                },
                $aObjects
            ),
        ];
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
        ];
    }
}
