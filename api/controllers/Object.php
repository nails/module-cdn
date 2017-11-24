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
            return ['data' => $aOut[0]];
        } else {
            return ['data' => $aOut];
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Upload a new object to the CDN
     * @todo Think about security here; there's probably a huge issue allowing anyone to upload anything to anywhere
     * @return array
     */
    public function postCreate()
    {
        $oInput = Factory::service('Input');
        $oCdn   = Factory::service('Cdn', 'nailsapp/module-cdn');
        $aOut   = [];

        // --------------------------------------------------------------------------

        if (!isLoggedIn()) {

            //  User is not logged in, they must supply a valid upload token
            $token = $oInput->post('token') ?: $oInput->get_request_header('X-cdn-token');
            $oUser = $oCdn->validateApiUploadToken($token);

            if (!$oUser) {
                $aOut['status'] = 400;
                $aOut['error']  = $oCdn->lastError();
                return $aOut;
            } else {
                $oUserModel = Factory::model('User', 'nailsapp/module-auth');
                $oUserModel->setActiveUser($oUser);
            }
        }

        // --------------------------------------------------------------------------

        //  Uploader verified; bucket defined?
        $sBucket = $oInput->post('bucket') ?: $oInput->get_request_header('X-cdn-bucket');

        if (!$sBucket) {
            $aOut['status'] = 400;
            $aOut['error']  = 'Bucket not defined.';
            return $aOut;
        }

        // --------------------------------------------------------------------------

        //  Attempt upload
        $oObject = $oCdn->objectCreate('upload', $sBucket);

        if ($oObject) {
            $aUrls          = $this->getRequestedUrls();
            $aOut['object'] = $this->formatObject($oObject, $aUrls);
        } else {
            $aOut['status'] = 400;
            $aOut['error']  = $oCdn->lastError();
        }

        return $aOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Delete an object from the CDN
     * @return array
     */
    public function postDelete()
    {
        /**
         * @todo Have a good think about security here, somehow verify that this
         * person has permission to delete objects. Perhaps only an objects creator
         * or a super user can delete. Maybe have a CDN permission?
         */

        $oInput = Factory::service('Input');
        $oCdn   = Factory::service('Cdn', 'nailsapp/module-cdn');

        //  Define $aOut array
        $aOut = [];

        // --------------------------------------------------------------------------

        $objectId = $oInput->post('object_id');
        $delete   = $oCdn->objectDelete($objectId);

        if (!$delete) {
            $aOut['status'] = 400;
            $aOut['error']  = $oCdn->lastError();
        }

        return $aOut;
    }

    // --------------------------------------------------------------------------

    protected function getRequestedUrls()
    {
        $oInput = Factory::service('Input');
        $sUrls  = $oInput->get('urls') ?: $oInput->get_request_header('X-cdn-urls');
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

    public function getSearch()
    {
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
            )
        ];
    }
}
