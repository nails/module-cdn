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

namespace Nails\Cdn\Api\Controller;

use Nails\Api\Controller\Base;
use Nails\Api\Exception\ApiException;
use Nails\Common\Service\Input;
use Nails\Cdn\Service\Cdn;
use Nails\Factory;

class CdnObject extends Base
{
    /**
     * The maximum number of objects a user can request at any one time
     */
    const MAX_OBJECTS_PER_REQUEST = 50;

    // --------------------------------------------------------------------------

    /**
     * Whether the user is authenticated. to be considered authenticated the user
     * must either have an active session or have passed a valid cdn token.
     *
     * @param string $sHttpMethod The HTTP Method protocol being used
     * @param string $sMethod     The controller method being executed
     *
     * @return boolean/array       Boolean true or false. Can also return an array
     *                             with two elements (status and error) which
     *                             will customise the response code and message.
     */
    public static function isAuthenticated($sHttpMethod = '', $sMethod = '')
    {
        if (isLoggedIn()) {
            return true;
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', 'nails/module-cdn');

        $sCdnToken = $oInput->header('X-Cdn-Token');
        return $oCdn->validateToken($sCdnToken);
    }

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
        $oCdn   = Factory::service('Cdn', 'nails/module-cdn');

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
            throw new ApiException(
                'You can request a maximum of ' . static::MAX_OBJECTS_PER_REQUEST . ' objects per request',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }
        // --------------------------------------------------------------------------

        //  Parse out any URLs requested
        $aUrls = $this->getRequestedUrls();

        // --------------------------------------------------------------------------

        $aOut     = [];
        $aResults = $oCdn->getObjects(
            0,
            static::MAX_OBJECTS_PER_REQUEST,
            [
                'where_in' => [
                    ['o.id', $aIds],
                ],
            ]
        );

        foreach ($aResults as $oObject) {
            $aOut[] = $this->formatObject($oObject, $aUrls);
        }

        return Factory::factory('ApiResponse', 'nails/module-api')
            ->setData($oInput->get('id') ? reset($aOut) : $aOut);
    }

    // --------------------------------------------------------------------------

    /**
     * Upload a new object to the CDN
     *
     * @return array
     */
    public function postCreate()
    {
        //  @todo (Pablo - 2017-12-18) - this should be protected using admin permissions or the token uploader
        $oHttpCodes = Factory::service('HttpCodes');
        $oInput     = Factory::service('Input');
        $oCdn       = Factory::service('Cdn', 'nails/module-cdn');
        $aOut       = [];

        // --------------------------------------------------------------------------

        $sBucket = $oInput->post('bucket') ?: $oInput->header('X-Cdn-Bucket');

        if (!$sBucket) {
            throw new ApiException(
                'Bucket not defined',
                $oHttpCodes::STATUS_BAD_REQUEST
            );
        }

        // --------------------------------------------------------------------------

        //  Attempt upload
        $oObject = $oCdn->objectCreate('upload', $sBucket);

        if (!$oObject) {
            throw new ApiException(
                $oCdn->lastError(),
                $oHttpCodes::STATUS_BAD_REQUEST
            );
        }

        //  @todo (Pablo - 2018-06-25) - Reduce the namespace here (i.e remove `object`)
        return Factory::factory('ApiResponse', 'nails/module-api')
            ->setData([
                'object' => $this->formatObject(
                    $oObject,
                    $this->getRequestedUrls()
                ),
            ]);
    }

    // --------------------------------------------------------------------------

    /**
     * Delete an object from the CDN
     *
     * @return array
     */
    public function postDelete()
    {
        $oHttpCodes = Factory::service('HttpCodes');

        if (!userHasPermission('admin:cdn:manager:object:delete')) {
            throw new ApiException(
                'You do not have permission to access this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        $oInput    = Factory::service('Input');
        $oCdn      = Factory::service('Cdn', 'nails/module-cdn');
        $iObjectId = $oInput->post('object_id');

        if (empty($iObjectId)) {
            throw new ApiException(
                '`object_id` is a required field',
                $oHttpCodes::STATUS_BAD_REQUEST
            );
        }

        $oObject  = $oCdn->getObject($iObjectId);
        $bIsTrash = false;
        if (empty($oObject)) {
            $oObject  = $oCdn->getObjectFromTrash($iObjectId);
            $bIsTrash = true;
        }

        if (empty($oObject)) {
            throw new ApiException(
                'Invalid object ID',
                $oHttpCodes::STATUS_NOT_FOUND
            );
        }

        if ($bIsTrash) {
            $bDelete = $oCdn->purgeTrash([$iObjectId]);
        } else {
            $bDelete = $oCdn->objectDelete($iObjectId);
        }

        if (!$bDelete) {
            throw new ApiException(
                $oCdn->lastError(),
                $oHttpCodes::STATUS_BAD_REQUEST
            );
        }

        return Factory::factory('ApiResponse', 'nails/module-api');
    }

    // --------------------------------------------------------------------------

    /**
     * Restore an item form the trash
     *
     * @return array
     */
    public function postRestore()
    {
        $oHttpCodes = Factory::service('HttpCodes');

        if (!userHasPermission('admin:cdn:manager:object:restore')) {
            throw new ApiException(
                'You do not have permission to access this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        $oInput    = Factory::service('Input');
        $oCdn      = Factory::service('Cdn', 'nails/module-cdn');
        $iObjectId = $oInput->post('object_id');

        if (!$oCdn->objectRestore($iObjectId)) {
            throw new ApiException(
                $oCdn->lastError(),
                $oHttpCodes::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        return Factory::factory('ApiResponse', 'nails/module-api');
    }

    // --------------------------------------------------------------------------

    /**
     * Search across all objects
     *
     * @return array
     */
    public function getSearch()
    {
        $oHttpCodes = Factory::service('HttpCodes');

        if (!userHasPermission('admin:cdn:manager:object:browse')) {
            throw new ApiException(
                'You do not have permission to access this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        $oInput    = Factory::service('Input');
        $oModel    = Factory::model('Object', 'nails/module-cdn');
        $sKeywords = $oInput->get('keywords');
        $iPage     = (int) $oInput->get('page') ?: 1;
        $oResult   = $oModel->search(
            $sKeywords,
            $iPage,
            static::MAX_OBJECTS_PER_REQUEST
        );

        $oResponse = Factory::factory('ApiResponse', 'nails/module-api');
        $oResponse->setData(array_map(
            function ($oObj) {
                $oObj->is_img = isset($oObj->img);
                $oObj->url    = (object) [
                    'src'     => cdnServe($oObj->id),
                    'preview' => isset($oObj->img) ? cdnCrop($oObj->id, 400, 400) : null,
                ];
                return $oObj;
            },
            $oResult->data
        ));
        return $oResponse;
    }

    // --------------------------------------------------------------------------

    /**
     * List items in the trash
     *
     * @return array
     */
    public function getTrash()
    {
        $oHttpCodes = Factory::service('HttpCodes');

        if (!userHasPermission('admin:cdn:manager:object:browse')) {
            throw new ApiException(
                'You do not have permission to access this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        $oInput   = Factory::service('Input');
        $oModel   = Factory::model('ObjectTrash', 'nails/module-cdn');
        $iPage    = (int) $oInput->get('page') ?: 1;
        $aResults = $oModel->getAll(
            $iPage,
            static::MAX_OBJECTS_PER_REQUEST,
            ['sort' => [['trashed', 'desc']]]
        );

        $oResponse = Factory::factory('ApiResponse', 'nails/module-api');
        $oResponse->setData(array_map(
            function ($oObj) {
                $oObj->is_img = isset($oObj->img);
                $oObj->url    = (object) [
                    'src'     => cdnServe($oObj->id),
                    'preview' => isset($oObj->img) ? cdnCrop($oObj->id, 400, 400) : null,
                ];
                return $oObj;
            },
            $aResults
        ));
        return $oResponse;
    }

    // --------------------------------------------------------------------------

    /**
     * Return an array of the requested URLs form the request
     *
     * @return array
     */
    protected function getRequestedUrls()
    {
        $oInput = Factory::service('Input');
        $sUrls  = $oInput->get('urls') ?: $oInput->header('X-Cdn-Urls');
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
        $oCdn = Factory::service('Cdn', 'nails/module-cdn');
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
}
