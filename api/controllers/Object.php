<?php

/**
 * Admin API end points: CDN objects
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Api\Cdn;

use Nails\Api\Controller\Base;
use Nails\Factory;

class Object extends Base
{
    /**
     * Require the user be authenticated to use any endpoint
     */
    const REQUIRE_AUTH = true;

    // --------------------------------------------------------------------------

    /**
     * The maximum number of objects a user can request at any one time
     */
    const MAX_OBJECTS_PER_REQUEST = 50;

    // --------------------------------------------------------------------------

    /**
     * Lists objects
     *
     * @return array
     */
    public function getIndex()
    {
        //  @todo (Pablo - 2017-12-18) - this should be protected using admin permissions or the token uploader
        $oInput = Factory::service('Input');
        $oCdn   = Factory::service('Cdn', 'nailsapp/module-cdn');

        $sIds = '';

        if (!empty($oInput->get('id'))) {
            $sIds = $oInput->get('id');
        }

        if (!empty($oInput->get('ids'))) {
            $sIds = $oInput->get('ids');
        }

        $aIds = !is_array($sIds) ? explode(',', $sIds) : $sIds;
        $aIds = array_filter($aIds);
        $aIds = array_unique($aIds);

        if (count($aIds) > 100) {
            return [
                'status' => 400,
                'error'  => 'You can request a maximum of ' . self::MAX_OBJECTS_PER_REQUEST . ' objects per request',
            ];
        }
        // --------------------------------------------------------------------------

        //  Parse out any URLs requested
        $aUrls = $this->getRequestedUrls();

        // --------------------------------------------------------------------------

        $aOut     = [];
        $aResults = $oCdn->getObjects(
            0,
            self::MAX_OBJECTS_PER_REQUEST,
            [
                'where_in' => [
                    ['o.id', $aIds],
                ],
            ]
        );

        foreach ($aResults as $oObject) {
            $aOut[] = $this->formatObject($oObject, $aUrls);
        }

        if ($oInput->get('id')) {
            return ['data' => reset($aOut)];
        } else {
            return ['data' => $aOut];
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Upload a new object to the CDN
     * @return array
     */
    public function postCreate()
    {
        //  @todo (Pablo - 2017-12-18) - this should be protected using admin permissions or the token uploader
        $oInput = Factory::service('Input');
        $oCdn   = Factory::service('Cdn', 'nailsapp/module-cdn');
        $aOut   = [];

        // --------------------------------------------------------------------------

        if (!isLoggedIn()) {

            //  User is not logged in, they must supply a valid upload token
            $sToken = $oInput->post('token') ?: $oInput->header('X-cdn-token');
            $oUser  = $oCdn->validateApiUploadToken($sToken);

            if (!$oUser) {
                return [
                    'status' => 400,
                    'error'  => $oCdn->lastError(),
                ];
            } else {
                $oUserModel = Factory::model('User', 'nailsapp/module-auth');
                $oUserModel->setActiveUser($oUser);
            }
        }

        // --------------------------------------------------------------------------

        //  Uploader verified; bucket defined?
        $sBucket = $oInput->post('bucket') ?: $oInput->header('X-cdn-bucket');

        if (!$sBucket) {
            return [
                'status' => 400,
                'error'  => 'Bucket not defined',
            ];
        }

        // --------------------------------------------------------------------------

        //  Attempt upload
        $oObject = $oCdn->objectCreate('upload', $sBucket);

        if (!$oObject) {
            return [
                'status' => 400,
                'error'  => $oCdn->lastError(),
            ];
        }

        $aUrls          = $this->getRequestedUrls();
        $aOut['object'] = $this->formatObject($oObject, $aUrls);

        return $aOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Delete an object from the CDN
     * @return array
     */
    public function postDelete()
    {
        if (!userHasPermission('admin:cdn:manager:object:delete')) {
            return [
                'status' => 401,
                'error'  => 'You do not have permission to delete objects',
            ];
        }

        $oInput    = Factory::service('Input');
        $oCdn      = Factory::service('Cdn', 'nailsapp/module-cdn');
        $iObjectId = $oInput->post('object_id');

        if (stringToBoolean($oInput->post('is_trash'))) {
            $bDelete = $oCdn->purgeTrash([$iObjectId]);
        } else {
            $bDelete = $oCdn->objectDelete($iObjectId);
        }

        if (!$bDelete) {
            return [
                'status' => 400,
                'error'  => $oCdn->lastError(),
            ];
        }

        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * Restore an item form the trash
     * @return array
     */
    public function postRestore()
    {
        if (!userHasPermission('admin:cdn:manager:object:restore')) {
            return [
                'status' => 401,
                'error'  => 'You do not have permission to restore objects',
            ];
        }

        $oInput    = Factory::service('Input');
        $oCdn      = Factory::service('Cdn', 'nailsapp/module-cdn');
        $iObjectId = $oInput->post('object_id');

        if (!$oCdn->objectRestore($iObjectId)) {
            return [
                'status' => 400,
                'error'  => $oCdn->lastError(),
            ];
        }

        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * Return an array of the requested URLs form the request
     * @return array
     */
    protected function getRequestedUrls()
    {
        $oInput = Factory::service('Input');
        $sUrls  = $oInput->get('urls') ?: $oInput->header('X-cdn-urls');
        $aUrls  = !is_array($sUrls) ? explode(',', $sUrls) : $sUrls;
        $aUrls  = array_map('strtolower', $aUrls);

        //  Filter out any which don't follow the format {digit}x{digit}-{scale|crop} || raw
        foreach ($aUrls as &$sDimension) {

            if (!is_string($sDimension)) {
                $sDimension = null;
                continue;
            }

            preg_match_all('/^(\d+?)x(\d+?)-(scale|crop)$/i', $sDimension, $aMatches);

            if (empty($aMatches[0])) {
                $sDimension = null;
            } else {
                $sDimension = [
                    'width'  => !empty($aMatches[1][0]) ? $aMatches[1][0] : null,
                    'height' => !empty($aMatches[2][0]) ? $aMatches[2][0] : null,
                    'type'   => !empty($aMatches[3][0]) ? $aMatches[3][0] : 'crop',
                ];
            }
        }

        return array_filter($aUrls);
    }

    // --------------------------------------------------------------------------

    /**
     * Format an object object
     *
     * @param \stdClass $oObject the object to format
     * @param array     $aUrls   The requested URLs
     *
     * @return object
     */
    protected function formatObject($oObject, $aUrls = [])
    {
        return (object) [
            'id'       => $oObject->id,
            'object'   => (object) [
                'name' => $oObject->file->name->human,
                'mime' => $oObject->file->mime,
                'size' => $oObject->file->size,
            ],
            'bucket'   => $oObject->bucket,
            'is_img'   => $oObject->is_img,
            'img'      => (object) [
                'width'       => $oObject->img_width,
                'height'      => $oObject->img_height,
                'orientation' => $oObject->img_orientation,
                'is_animated' => $oObject->is_animated,
            ],
            'created'  => $oObject->created,
            'modified' => $oObject->modified,
            'url'      => (object) $this->generateUrls($oObject, $aUrls),
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Generate the requested URLs
     *
     * @param \stdClass $oObject The object object
     * @param array     $aUrls   The URLs to generate
     *
     * @return array
     */
    protected function generateUrls($oObject, $aUrls)
    {
        $oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');
        $aOut = ['src' => $oCdn->urlServe($oObject)];

        if (!empty($aUrls) && $oObject->is_img) {
            foreach ($aUrls as $aDimension) {

                $sProperty = $aDimension['width'] . 'x' . $aDimension['height'] . '-' . $aDimension['type'];

                switch ($aDimension['type']) {
                    case 'crop':
                        $aOut[$sProperty] = $oCdn->urlCrop(
                            $oObject,
                            $aDimension['width'],
                            $aDimension['height']
                        );
                        break;

                    case 'scale':
                        $aOut[$sProperty] = $oCdn->urlScale(
                            $oObject,
                            $aDimension['width'],
                            $aDimension['height']
                        );
                        break;
                }
            }
        }

        return $aOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Search across all objects
     * @return array
     */
    public function getSearch()
    {
        if (!userHasPermission('admin:cdn:manager:object:browse')) {
            return [
                'status' => 401,
                'error'  => 'You do not have permission to search objects',
            ];
        }

        $oInput    = Factory::service('Input');
        $oModel    = Factory::model('Object', 'nailsapp/module-cdn');
        $sKeywords = $oInput->get('keywords');
        $iPage     = (int) $oInput->get('page') ?: 1;
        $oResults  = $oModel->search(
            $sKeywords,
            $iPage,
            static::MAX_OBJECTS_PER_REQUEST
        );

        return [
            'data' => array_map(
                function ($oObj) {
                    $oObj->is_img = isset($oObj->img);
                    $oObj->url    = (object) [
                        'src'     => cdnServe($oObj->id),
                        'preview' => isset($oObj->img) ? cdnCrop($oObj->id, 400, 400) : null,
                    ];
                    return $oObj;
                },
                $oResults->data
            ),
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * List items in the trash
     * @return array
     */
    public function getTrash()
    {
        if (!userHasPermission('admin:cdn:manager:object:browse')) {
            return [
                'status' => 401,
                'error'  => 'You do not have permission to browse objects',
            ];
        }

        $oInput   = Factory::service('Input');
        $oModel   = Factory::model('ObjectTrash', 'nailsapp/module-cdn');
        $iPage    = (int) $oInput->get('page') ?: 1;
        $aResults = $oModel->getAll(
            $iPage,
            static::MAX_OBJECTS_PER_REQUEST,
            ['sort' => [['trashed', 'desc']]]
        );

        return [
            'data' => array_map(
                function ($oObj) {
                    $oObj->is_img = isset($oObj->img);
                    $oObj->url    = (object) [
                        'src'     => cdnServe($oObj->id),
                        'preview' => isset($oObj->img) ? cdnCrop($oObj->id, 400, 400) : null,
                    ];
                    return $oObj;
                },
                $aResults
            ),
        ];
    }
}
