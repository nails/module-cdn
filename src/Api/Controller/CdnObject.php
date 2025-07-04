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

use Nails\Api;
use Nails\Cdn\Constants;
use Nails\Cdn\Model\CdnObject\Trash;
use Nails\Cdn\Service\Cdn;
use Nails\Cdn\Service\Monitor;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Helper\Model\Expand;
use Nails\Common\Service\HttpCodes;
use Nails\Common\Service\Input;
use Nails\Factory;

/**
 * Class CdnObject
 *
 * @package Nails\Cdn\Api\Controller
 */
class CdnObject extends Api\Controller\Base
{
    /**
     * The maximum number of objects a user can request at any one time
     *
     * @var int
     */
    const MAX_OBJECTS_PER_REQUEST = 50;

    // --------------------------------------------------------------------------

    /**
     * Whether the user is authenticated. To be considered authenticated the user
     * must either have an active session or have passed a valid CDN token.
     *
     * @param string $sHttpMethod The HTTP Method protocol being used
     * @param string $sMethod     The controller method being executed
     *
     * @return bool|array       Boolean true or false. Can also return an array
     *                          with two elements (status and error) which
     *                          will customise the response code and message.
     */
    public static function isAuthenticated($sHttpMethod = '', $sMethod = ''): bool
    {
        if (($sHttpMethod === 'GET' && $sMethod === 'index') || isLoggedIn()) {
            return true;
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);

        $sCdnToken = $oInput->header('X-Cdn-Token');
        return $oCdn->validateToken($sCdnToken ?: null);
    }

    // --------------------------------------------------------------------------

    /**
     * Lists objects
     *
     * @return Api\Factory\ApiResponse
     * @throws Api\Exception\ApiException
     * @throws FactoryException
     */
    public function getIndex(): Api\Factory\ApiResponse
    {
        //  @todo (Pablo - 2019-09-18) - Consider a way to restrict abuse of this endpoint
        //  See: https://github.com/nails/module-cdn/issues/76

        // --------------------------------------------------------------------------

        /** @var HttpCodes $oHttpCodes */
        $oHttpCodes = Factory::service('HttpCodes');
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);

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
            throw new Api\Exception\ApiException(
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

        return Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG)
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
        /** @var HttpCodes $oHttpCodes */
        $oHttpCodes = Factory::service('HttpCodes');
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        $aOut = [];

        // --------------------------------------------------------------------------

        $sBucket = $oInput->post('bucket') ?: $oInput->header('X-Cdn-Bucket');

        if (!$sBucket) {
            throw new Api\Exception\ApiException(
                'Bucket not defined',
                $oHttpCodes::STATUS_BAD_REQUEST
            );
        }

        // --------------------------------------------------------------------------

        //  Attempt upload
        $oObject = $oCdn->objectCreate(
            'upload',
            $sBucket,
            $oCdn->apiUploadOptions()
        );

        if (!$oObject) {
            throw new Api\Exception\ApiException(
                $oCdn->lastError(),
                $oHttpCodes::STATUS_BAD_REQUEST
            );
        }

        //  @todo (Pablo - 2018-06-25) - Reduce the namespace here (i.e remove `object`)
        return Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG)
            ->setData([
                'object' => $this->formatObject(
                    $oObject,
                    $this->getRequestedUrls()
                ),
            ]);
    }

    // --------------------------------------------------------------------------

    /**
     * Edit an object from the CDN
     *
     * @return Api\Factory\ApiResponse
     */
    public function postEdit()
    {
        /** @var HttpCodes $oHttpCodes */
        $oHttpCodes = Factory::service('HttpCodes');
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        /** @var \Nails\Cdn\Model\CdnObject $oModel */
        $oModel = Factory::model('Object', constants::MODULE_SLUG);

        if (!userHasPermission('admin:cdn:manager:object:create')) {
            throw new Api\Exception\ApiException(
                'You do not have permission to access this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        $oObject   = $oModel->getById($oInput->post('object_id'));
        $sFileName = trim((string) $oInput->post('filename_display'));
        $sMetaData = json_encode($oInput->post('metadata') ?: []);

        if (empty($oObject)) {
            throw new Api\Exception\ApiException('Object not found', $oHttpCodes::STATUS_NOT_FOUND);

        } elseif (empty($sFileName)) {
            throw new Api\Exception\ApiException('`filename_display` is required', $oHttpCodes::STATUS_BAD_REQUEST);
        }

        $sExpectedExtension = $oObject->file->ext;

        //  Ensure filename has correct extension
        $sFileExtension = strtolower(pathinfo($sFileName, PATHINFO_EXTENSION));
        if (empty($sFileExtension) || $sFileExtension !== $sExpectedExtension) {
            $sFileName .= '.' . $sExpectedExtension;
        }

        $bResult = $oModel->update($oObject->id, [
            'filename_display' => $sFileName,
            'metadata'         => $sMetaData,
        ]);

        if (!$bResult) {
            throw new Api\Exception\ApiException('Failed to update object. ' . $oModel->lastError(), $oHttpCodes::STATUS_INTERNAL_SERVER_ERROR);
        }

        //  @todo (Pablo - 2018-06-25) - Reduce the namespace here (i.e remove `object`)
        return Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG)
            ->setData([
                'object' => $this->formatObject(
                    $oCdn->getObject($oObject->id),
                    $this->getRequestedUrls()
                ),
            ]);
    }

    // --------------------------------------------------------------------------

    /**
     * Delete an object from the CDN
     *
     * @return Api\Factory\ApiResponse
     * @throws Api\Exception\ApiException
     * @throws FactoryException
     */
    public function postDelete(): Api\Factory\ApiResponse
    {
        /** @var HttpCodes $oHttpCodes */
        $oHttpCodes = Factory::service('HttpCodes');

        if (!userHasPermission('admin:cdn:manager:object:delete')) {
            throw new Api\Exception\ApiException(
                'You do not have permission to access this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);

        $aData     = $this->getRequestData();
        $iObjectId = $aData['object_id'] ?? null;

        if (empty($iObjectId)) {
            throw new Api\Exception\ApiException(
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
            throw new Api\Exception\ApiException(
                'Invalid object ID',
                $oHttpCodes::STATUS_NOT_FOUND
            );
        }

        if (!$bIsTrash) {
            /** @var Monitor $oMonitor */
            $oMonitor = Factory::service('Monitor', Constants::MODULE_SLUG);
            /** @var \Nails\Cdn\Model\CdnObject $oObjectModel */
            $oObjectModel = Factory::model('Object', constants::MODULE_SLUG);

            $aLocations = $oMonitor->locate($oObjectModel->getById($iObjectId, [new Expand('bucket')]));
            if (!empty($aLocations)) {
                throw new Api\Exception\ApiException(
                    'Object is in use',
                    $oHttpCodes::STATUS_BAD_REQUEST
                );
            }
        }

        $bDelete = $bIsTrash
            ? $oCdn->purgeTrash([$iObjectId])
            : $oCdn->objectDelete($iObjectId);

        if (!$bDelete) {
            throw new Api\Exception\ApiException(
                $oCdn->lastError(),
                $oHttpCodes::STATUS_BAD_REQUEST
            );
        }

        return Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG);
    }

    // --------------------------------------------------------------------------

    /**
     * Restore an item form the trash
     *
     * @return Api\Factory\ApiResponse
     * @throws Api\Exception\ApiException
     * @throws FactoryException
     */
    public function postRestore(): Api\Factory\ApiResponse
    {
        /** @var HttpCodes $oHttpCodes */
        $oHttpCodes = Factory::service('HttpCodes');

        if (!userHasPermission('admin:cdn:manager:object:restore')) {
            throw new Api\Exception\ApiException(
                'You do not have permission to access this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);

        $iObjectId = $oInput->post('object_id');

        if (!$oCdn->objectRestore($iObjectId)) {
            throw new Api\Exception\ApiException(
                $oCdn->lastError(),
                $oHttpCodes::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        return Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG);
    }

    // --------------------------------------------------------------------------

    /**
     * Search across all objects
     *
     * @return Api\Factory\ApiResponse
     * @throws Api\Exception\ApiException
     * @throws FactoryException
     */
    public function getSearch(): Api\Factory\ApiResponse
    {
        /** @var HttpCodes $oHttpCodes */
        $oHttpCodes = Factory::service('HttpCodes');

        if (!userHasPermission('admin:cdn:manager:object:browse')) {
            throw new Api\Exception\ApiException(
                'You do not have permission to access this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var \Nails\Cdn\Model\CdnObject $oModel */
        $oModel = Factory::model('Object', Constants::MODULE_SLUG);

        $sKeywords = $oInput->get('keywords');
        $iPage     = (int) $oInput->get('page') ?: 1;
        $oResult   = $oModel->search(
            $sKeywords,
            $iPage,
            static::MAX_OBJECTS_PER_REQUEST
        );

        /** @var Api\Factory\ApiResponse $oResponse */
        $oResponse = Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG);
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
     * @return Api\Factory\ApiResponse
     * @throws Api\Exception\ApiException
     * @throws FactoryException
     * @throws ModelException
     */
    public function getTrash(): Api\Factory\ApiResponse
    {
        /** @var HttpCodes $oHttpCodes */
        $oHttpCodes = Factory::service('HttpCodes');

        if (!userHasPermission('admin:cdn:manager:object:browse')) {
            throw new Api\Exception\ApiException(
                'You do not have permission to access this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Trash $oModel */
        $oModel = Factory::model('ObjectTrash', Constants::MODULE_SLUG);

        $iPage    = (int) $oInput->get('page') ?: 1;
        $aResults = $oModel->getAll(
            $iPage,
            static::MAX_OBJECTS_PER_REQUEST,
            ['sort' => [['trashed', 'desc']]]
        );

        /** @var Api\Factory\ApiResponse $oResponse */
        $oResponse = Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG);
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
     * @throws FactoryException
     */
    protected function getRequestedUrls(): array
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');

        $sUrls = $oInput->get('urls') ?: $oInput->header('X-Cdn-Urls');
        $aUrls = !is_array($sUrls) ? explode(',', $sUrls) : $sUrls;
        $aUrls = array_map('strtolower', $aUrls);

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
     * @return \stdClass
     */
    protected function formatObject($oObject, $aUrls = []): \stdClass
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
            'metadata' => $oObject->metadata,
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
     * @throws FactoryException
     */
    protected function generateUrls($oObject, $aUrls): array
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);

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
