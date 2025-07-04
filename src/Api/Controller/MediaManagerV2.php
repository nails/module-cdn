<?php

/**
 * Admin API end points: Media Manager V2
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
use Nails\Auth\Model\User;
use Nails\Cdn\Constants;
use Nails\Cdn\Service\Cdn;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Exception\ValidationException;
use Nails\Common\Helper\ArrayHelper;
use Nails\Common\Helper\Model\Condition;
use Nails\Common\Helper\Model\Expand;
use Nails\Common\Helper\Model\Limit;
use Nails\Common\Helper\Model\Sort;
use Nails\Common\Helper\Model\Where;
use Nails\Common\Helper\Model\WhereIn;
use Nails\Common\Service\Database;
use Nails\Common\Service\FormValidation;
use Nails\Common\Service\HttpCodes;
use Nails\Common\Service\Input;
use Nails\Common\Service\Mime;
use Nails\Factory;

/**
 * Class MediaManagerV2
 *
 * @package Nails\Cdn\Api\Controller
 */
class MediaManagerV2 extends Api\Controller\Base
{
    const REQUIRE_AUTH = true;

    public static function isAuthenticated($sHttpMethod = '', $sMethod = '')
    {
        return parent::isAuthenticated($sHttpMethod, $sMethod) && userHasPermission('admin:cdn:manager:object:browse');
    }

    public function getFileTypes(): ApiResponse
    {
        /** @var Mime $oMime */
        $oMime = Factory::service('Mime');
        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        /** @var \Nails\Cdn\Model\CdnObject $oObjectModel */
        $oObjectModel = Factory::model('Object', Constants::MODULE_SLUG);
        /** @var \Nails\Cdn\Model\Bucket $oBucketModel */
        $oBucketModel = Factory::model('Bucket', Constants::MODULE_SLUG);

        $aAvailableMimes = ArrayHelper::extract(
            $oDb
                ->select('DISTINCT(mime)')
                ->where(
                    sprintf(
                        '(SELECT is_hidden FROM %s b WHERE b.id = %s.bucket_id) = 0',
                        $oBucketModel->getTableName(),
                        $oObjectModel->getTableName()
                    ),
                    false,
                    false
                )
                ->get($oObjectModel->getTableName())
                ->result(),
            'mime'
        );

        $aMimeGroups = $oMime->getMimeGroups($aAvailableMimes);
        $aOut        = [];

        foreach ($aMimeGroups as $sLabel => $aMimes) {
            $aOut[] = ['id' => $sLabel, 'label' => $sLabel];
        };

        /** @var ApiResponse $oResponse */
        $oResponse = Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG);
        $oResponse->setData($aOut);
        return $oResponse;
    }

    public function getUploaders(): ApiResponse
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        /** @var User $oUserModel */
        $oUserModel = Factory::model('User', \Nails\Auth\Constants::MODULE_SLUG);
        /** @var \Nails\Cdn\Model\CdnObject $oObjectModel */
        $oObjectModel = Factory::model('Object', Constants::MODULE_SLUG);
        /** @var \Nails\Cdn\Model\Bucket $oBucketModel */
        $oBucketModel = Factory::model('Bucket', Constants::MODULE_SLUG);

        $aUserIds = ArrayHelper::extract(
            $oDb
                ->select('DISTINCT(created_by)')
                ->where(
                    sprintf(
                        '(SELECT is_hidden FROM %s b WHERE b.id = %s.bucket_id) = 0',
                        $oBucketModel->getTableName(),
                        $oObjectModel->getTableName()
                    ),
                    false,
                    false
                )
                ->get($oObjectModel->getTableName())
                ->result(),
            'created_by'
        );
        $iTotal   = count($aUserIds);

        $iPerPage = 50;
        $iPage    = (int) $oInput->get('page') ?: 1;
        $iPage    = $iPage < 0 ? $iPage * -1 : $iPage;

        $aUsers = array_map(
            fn(\Nails\Auth\Resource\User $oUser) => [
                'id'    => $oUser->id,
                'label' => $oUser->name ?: 'User ID #' . $oUser->id,
                'email' => $oUser->email,
            ],
            $oUserModel->getByIds($aUserIds, [
                new Limit($iPerPage, $iPage),
                new Sort('first_name'),
            ])
        );

        ArrayHelper::arraySortMulti($aUsers, 'label');
        $aUsers = array_values($aUsers);

        /** @var ApiResponse $oResponse */
        $oResponse = Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG);
        $oResponse
            ->setData($aUsers)
            ->setMeta([
                'pagination' => [
                    'page'     => $iPage,
                    'per_page' => $iPerPage,
                    'total'    => $iTotal,
                    'previous' => $this->buildUrl($iTotal, $iPerPage, $iPage, -1),
                    'next'     => $this->buildUrl($iTotal, $iPerPage, $iPage, 1),
                ],
            ]);

        return $oResponse;
    }

    public function getBuckets(): ApiResponse
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var \Nails\Cdn\Model\Bucket $oBucketModel */
        $oBucketModel = Factory::model('Bucket', Constants::MODULE_SLUG);

        $iPerPage = 50;
        $iPage    = (int) $oInput->get('page') ?: 1;
        $iPage    = $iPage < 0 ? $iPage * -1 : $iPage;

        $aConditions = [
            new Limit($iPerPage, $iPage),
            new Sort('label'),
            new Where('is_hidden', false),
        ];

        $iTotal   = $oBucketModel->countAll($aConditions);
        $aBuckets = $oBucketModel->getAll($aConditions);

        /** @var ApiResponse $oResponse */
        $oResponse = Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG);
        $oResponse
            ->setData(array_map(
                fn(\Nails\Cdn\Resource\Bucket $oBucket) => [
                    'id'    => $oBucket->id,
                    'label' => $oBucket->label,
                ],
                $aBuckets
            ))
            ->setMeta([
                'pagination' => [
                    'page'     => $iPage,
                    'per_page' => $iPerPage,
                    'total'    => $iTotal,
                    'previous' => $this->buildUrl($iTotal, $iPerPage, $iPage, -1),
                    'next'     => $this->buildUrl($iTotal, $iPerPage, $iPage, 1),
                ],
            ]);

        return $oResponse;
    }

    public function getObjects(bool $trashed = false): ApiResponse
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Mime $oMime */
        $oMime = Factory::service('Mime');
        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        /** @var FormValidation $oFormValidation */
        $oFormValidation = Factory::service('FormValidation');
        /** @var \Nails\Cdn\Model\CdnObject|\Nails\Cdn\Model\CdnObject\Trash $oObjectModel */
        $oObjectModel = $trashed
            ? Factory::model('ObjectTrash', Constants::MODULE_SLUG)
            : Factory::model('Object', Constants::MODULE_SLUG);
        /** @var \Nails\Cdn\Model\Bucket $oBucketModel */
        $oBucketModel = Factory::model('Bucket', Constants::MODULE_SLUG);

        $aFilters = [
            'keywords'    => trim($oInput->get('keywords') ?: ''),
            'bucket_ids'  => array_map('intval', $oInput->get('buckets') ?: []),
            'mime_groups' => array_map('trim', $oInput->get('fileTypes') ?: []),
            'user_ids'    => array_map('intval', $oInput->get('uploaders') ?: []),
            'date_lower'  => trim($oInput->get('dateLower') ?: ''),
            'date_upper'  => trim($oInput->get('dateUpper') ?: ''),
        ];

        /** @var ApiResponse $oResponse */
        $oResponse = Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG);

        try {

            $oFormValidation
                ->buildValidator([
                    'date_lower' => [
                        $oFormValidation::RULE_VALID_DATE,
                    ],
                    'date_upper' => [
                        $oFormValidation::RULE_VALID_DATE,
                    ],
                ])
                ->run($aFilters);

            $iPerPage = 25;
            $iPage    = (int) $oInput->get('page') ?: 1;
            $iPage    = $iPage < 0 ? $iPage * -1 : $iPage;

            $aConditions = array_filter([
                new Limit($iPerPage, $iPage),
                new Sort($trashed ? 'trashed' : 'created', Sort::DESC),
                new Expand('bucket'),
                new Expand('created_by'),
                new Where(
                    sprintf(
                        '(SELECT is_hidden FROM `%s` b WHERE b.id = %s.bucket_id)',
                        $oBucketModel->getTableName(),
                        $oObjectModel->getTableAlias()
                    ),
                    false
                ),
                !empty($aFilters['keywords'])
                    ? new Condition(implode(' OR ', array_filter([
                    is_numeric($aFilters['keywords']) ? 'id = ' . $aFilters['keywords'] : null,
                    'filename_display LIKE "%' . $oDb->escape_like_str($aFilters['keywords']) . '%"',
                    'filename LIKE "%' . $oDb->escape_like_str($aFilters['keywords']) . '%"',
                    'metadata LIKE "%' . $oDb->escape_like_str($aFilters['keywords']) . '%"',
                    'JSON_SEARCH(JSON_EXTRACT(metadata, \'$[*].key\'), \'one\', \'%key%\') IS NOT NULL',
                    'JSON_SEARCH(JSON_EXTRACT(metadata, \'$[*].value\'), \'one\', \'%key%\') IS NOT NULL',
                ])))
                    : null,
                !empty($aFilters['bucket_ids'])
                    ? new WhereIn('bucket_id', $aFilters['bucket_ids'])
                    : null,
                !empty($aFilters['mime_groups'])
                    ? new WhereIn('mime', $oMime->getMimesForGroups($aFilters['mime_groups']))
                    : null,
                !empty($aFilters['user_ids'])
                    ? new WhereIn('created_by', $aFilters['user_ids'])
                    : null,
                !empty($aFilters['date_lower'])
                    ? new Where('DATE(created) >=', $aFilters['date_lower'])
                    : null,
                !empty($aFilters['date_upper'])
                    ? new Where('DATE(created) <=', $aFilters['date_upper'])
                    : null,
            ]);

            $iTotal   = $oObjectModel->countAll($aConditions);
            $aObjects = $oObjectModel->getAll($aConditions);

            $oResponse
                ->setData(array_map(
                    fn(\Nails\Cdn\Resource\CdnObject $oObject) => $this->formatObject($oObject),
                    $aObjects
                ))
                ->setMeta([
                    'pagination' => [
                        'page'     => $iPage,
                        'per_page' => $iPerPage,
                        'total'    => $iTotal,
                        'previous' => $this->buildUrl($iTotal, $iPerPage, $iPage, -1),
                        'next'     => $this->buildUrl($iTotal, $iPerPage, $iPage, 1),
                    ],
                ]);

        } catch (ValidationException $e) {
            dd($e);
        }

        return $oResponse;
    }

    public function getTrash(): ApiResponse
    {
        return $this->getObjects(trashed: true);
    }

    public function postRestore(): ApiResponse
    {
        /** @var HttpCodes $oHttpCodes */
        $oHttpCodes = Factory::service('HttpCodes');

        if (!userHasPermission('admin:cdn:manager:object:restore')) {
            throw new Api\Exception\ApiException(
                'You do not have permission to access this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);

        $aData      = $this->getRequestData();
        $aObjectIds = $aData['object_ids'] ?? [];
        $aSuccess   = [];
        $aError     = [];

        foreach ($aObjectIds as $iObjectId) {
            try {

                if (!$oCdn->objectRestore($iObjectId)) {
                    throw new Api\Exception\ApiException(
                        $oCdn->lastError(),
                        $oHttpCodes::STATUS_INTERNAL_SERVER_ERROR
                    );
                }

                $aSuccess[] = $iObjectId;

            } catch (\Exception $e) {
                $aError[] = ['id' => $iObjectId, 'error' => $e->getMessage()];
            }
        }

        /** @var ApiResponse $oApiResponse */
        $oApiResponse = Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG);
        return $oApiResponse
            ->setData([
                'success' => $aSuccess,
                'error'   => $aError,
            ]);
    }

    protected function formatObject(\Nails\Cdn\Resource\CdnObject $oObject): array
    {
        /** @var Mime $oMime */
        $oMime = Factory::service('Mime');

        return [
            'id'         => $oObject->id,
            'file'       => $oObject->file,
            'group'      => $oMime->getGroupForMime($oObject->file->mime),
            'bucket'     => [
                'id'    => $oObject->bucket->id,
                'label' => $oObject->bucket->label,
            ],
            'is_img'     => $oObject->is_img,
            'url'        => [
                'src'      => $oObject->url->src,
                'download' => $oObject->url->download,
                'thumb'    => $oObject->is_img ? [
                    'list' => cdnCrop($oObject->id, 120, 120),
                    'grid' => cdnCrop($oObject->id, 400, 400),
                ] : null,
            ],
            'metadata'   => $oObject->metadata,
            'created'    => $oObject->created,
            'created_by' => $oObject->created_by ? [
                'id'    => $oObject->created_by->id,
                'name'  => $oObject->created_by->name,
                'email' => $oObject->created_by->email,
            ] : null,
        ];
    }

    protected function buildUrl($iTotal, $iPerPage, $iPage, $iPageOffset)
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');

        $aParams = array_merge(
            $oInput->get(),
            [
                'page' => $iPage + $iPageOffset,
            ]
        );

        if ($aParams['page'] <= 0) {
            return null;
        } elseif ($aParams['page'] === 1) {
            unset($aParams['page']);
        }

        $iTotalPages = ceil($iTotal / $iPerPage);
        if (!empty($aParams['page']) && $aParams['page'] > $iTotalPages) {
            return null;
        }

        $sUrl = siteUrl() . uri_string();

        if (!empty($aParams)) {
            $sUrl .= '?' . http_build_query($aParams);
        }

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * @throws Api\Exception\ApiException
     * @throws FactoryException
     * @throws ModelException
     * @throws ValidationException
     */
    public function postReplace(): ApiResponse
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var HttpCodes $oHttpCodes */
        $oHttpCodes = Factory::service('HttpCodes');
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        /** @var \Nails\Cdn\Model\CdnObject $oObjectModel */
        $oObjectModel = Factory::model('Object', Constants::MODULE_SLUG);

        if (!userHasPermission('admin:cdn:manager:object:replace')) {
            throw new Api\Exception\ApiException(
                'You do not have permission to access this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        /** @var \Nails\Cdn\Resource\CdnObject|null $oObject */
        $oObject = $oObjectModel->getById($oInput->post('object_id'), [
            new Expand('bucket'),
            new Expand('created_by'),
        ]);

        if (empty($oObject)) {
            throw new Api\Exception\ApiException('Object not found', $oHttpCodes::STATUS_NOT_FOUND);
        }

        if (!$oCdn->objectReplace($oObject->id, 'file')) {
            throw new Api\Exception\ApiException($oCdn->lastError(), $oHttpCodes::STATUS_BAD_REQUEST);
        }

        /** @var \Nails\Cdn\Resource\CdnObject $oObject */
        $oObject = $oObjectModel->skipCache()->getById($oInput->post('object_id'), [
            new Expand('bucket'),
            new Expand('created_by'),
        ]);

        /** @var ApiResponse $oResponse */
        $oResponse = Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG);
        $oResponse->setData($this->formatObject($oObject));

        return $oResponse;
    }

    /**
     * Move an object to a different bucket
     *
     * @throws Api\Exception\ApiException
     * @throws FactoryException
     * @throws ModelException
     * @throws ValidationException
     */
    public function postMove(): ApiResponse
    {
        /** @var HttpCodes $oHttpCodes */
        $oHttpCodes = Factory::service('HttpCodes');
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        /** @var \Nails\Cdn\Model\CdnObject $oObjectModel */
        $oObjectModel = Factory::model('Object', Constants::MODULE_SLUG);
        /** @var \Nails\Cdn\Model\Bucket $oBucketModel */
        $oBucketModel = Factory::model('Bucket', Constants::MODULE_SLUG);

        if (!userHasPermission('admin:cdn:manager:object:move')) {
            throw new Api\Exception\ApiException(
                'You do not have permission to access this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        // Validate input
        $data            = $this->getRequestData();
        $iObjectId       = (int) ($data['object_id'] ?? null);
        $iTargetBucketId = (int) ($data['bucket_id'] ?? null);

        if (empty($iObjectId)) {
            throw new Api\Exception\ApiException('Object ID is required', $oHttpCodes::STATUS_BAD_REQUEST);
        }

        if (empty($iTargetBucketId)) {
            throw new Api\Exception\ApiException('Bucket ID is required', $oHttpCodes::STATUS_BAD_REQUEST);
        }

        // Get the object
        /** @var \Nails\Cdn\Resource\CdnObject|null $oObject */
        $oObject = $oObjectModel->getById($iObjectId, [
            new Expand('bucket'),
            new Expand('created_by'),
        ]);

        if (empty($oObject)) {
            throw new Api\Exception\ApiException('Object not found', $oHttpCodes::STATUS_NOT_FOUND);
        }

        // Get the destination bucket
        /** @var \Nails\Cdn\Resource\Bucket|null $oTargetBucket */
        $oTargetBucket = $oBucketModel->getById($iTargetBucketId);
        if (empty($oTargetBucket)) {
            throw new Api\Exception\ApiException('Destination bucket not found', $oHttpCodes::STATUS_NOT_FOUND);
        }

        // Check if the object is already in the destination bucket
        if ($oObject->bucket->id === $oTargetBucket->id) {
            throw new Api\Exception\ApiException('Object is already in the destination bucket', $oHttpCodes::STATUS_BAD_REQUEST);
        }

        // Move the object to the new bucket
        $oUpdatedObject = $oCdn->objectMove($oObject, $oTargetBucket);
        if (!$oUpdatedObject) {
            throw new Api\Exception\ApiException('Failed to copy object: ' . $oCdn->lastError(), $oHttpCodes::STATUS_INTERNAL_SERVER_ERROR);
        }

        /** @var ApiResponse $oResponse */
        $oResponse = Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG);
        $oResponse->setData($this->formatObject($oUpdatedObject));

        return $oResponse;
    }

    /**
     * Copy an object to a different bucket
     *
     * @throws Api\Exception\ApiException
     * @throws FactoryException
     * @throws ModelException
     * @throws ValidationException
     */
    public function postCopy(): ApiResponse
    {
        /** @var HttpCodes $oHttpCodes */
        $oHttpCodes = Factory::service('HttpCodes');
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        /** @var \Nails\Cdn\Model\CdnObject $oObjectModel */
        $oObjectModel = Factory::model('Object', Constants::MODULE_SLUG);
        /** @var \Nails\Cdn\Model\Bucket $oBucketModel */
        $oBucketModel = Factory::model('Bucket', Constants::MODULE_SLUG);

        if (!userHasPermission('admin:cdn:manager:object:copy')) {
            throw new Api\Exception\ApiException(
                'You do not have permission to access this resource',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        // Validate input
        $data            = $this->getRequestData();
        $iObjectId       = (int) ($data['object_id'] ?? null);
        $iTargetBucketId = (int) ($data['bucket_id'] ?? null);

        if (empty($iObjectId)) {
            throw new Api\Exception\ApiException('Object ID is required', $oHttpCodes::STATUS_BAD_REQUEST);
        }

        if (empty($iTargetBucketId)) {
            throw new Api\Exception\ApiException('Bucket ID is required', $oHttpCodes::STATUS_BAD_REQUEST);
        }

        // Get the object
        /** @var \Nails\Cdn\Resource\CdnObject|null $oObject */
        $oObject = $oObjectModel->getById($iObjectId, [
            new Expand('bucket'),
            new Expand('created_by'),
        ]);

        if (empty($oObject)) {
            throw new Api\Exception\ApiException('Object not found', $oHttpCodes::STATUS_NOT_FOUND);
        }

        // Get the destination bucket
        /** @var \Nails\Cdn\Resource\Bucket|null $oTargetBucket */
        $oTargetBucket = $oBucketModel->getById($iTargetBucketId);

        if (empty($oTargetBucket)) {
            throw new Api\Exception\ApiException('Destination bucket not found', $oHttpCodes::STATUS_NOT_FOUND);
        }

        // Copy the object to the new bucket
        $oNewObject = $oCdn->objectCopy($oObject, $oTargetBucket);
        if (!$oNewObject) {
            throw new Api\Exception\ApiException('Failed to copy object: ' . $oCdn->lastError(), $oHttpCodes::STATUS_INTERNAL_SERVER_ERROR);
        }

        /** @var ApiResponse $oResponse */
        $oResponse = Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG);
        $oResponse->setData($this->formatObject($oNewObject));

        return $oResponse;
    }
}
