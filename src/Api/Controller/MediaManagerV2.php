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

    public function getObjects(): ApiResponse
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Mime $oMime */
        $oMime = Factory::service('Mime');
        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        /** @var FormValidation $oFormValidation */
        $oFormValidation = Factory::service('FormValidation');
        /** @var \Nails\Cdn\Model\CdnObject $oObjectModel */
        $oObjectModel = Factory::model('Object', Constants::MODULE_SLUG);
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
                new Sort('created', Sort::DESC),
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

    public function postReplace(): ApiResponse
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var \Nails\Cdn\Model\CdnObject $oObjectModel */
        $oObjectModel = Factory::model('Object', Constants::MODULE_SLUG);

        $oObject = $oObjectModel->getById($oInput->post('object_id'), [
            new Expand('bucket'),
            new Expand('created_by'),
        ]);

        if (empty($oObject)) {
            throw new Api\Exception\ApiException('Object not found', $oHttpCodes::STATUS_NOT_FOUND);
        }

        /** @var ApiResponse $oResponse */
        $oResponse = Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG);
        $oResponse->setData($this->formatObject($oObject));
        
        return $oResponse;
    }
}
