<?php

/**
 * Manage CDN Buckets
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    AdminController
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Cdn\Admin\Controller;

use Nails\Admin\Controller\Base;
use Nails\Admin\Factory\Nav;
use Nails\Admin\Helper;
use Nails\Cdn\Admin\Permission;
use Nails\Cdn\Constants;
use Nails\Cdn\Exception\CdnException;
use Nails\Cdn\Model\Bucket;
use Nails\Cdn\Admin\Permission\Object\Import;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Exception\NailsException;
use Nails\Common\Exception\ValidationException;
use Nails\Common\Factory\HttpRequest\Head;
use Nails\Common\Helper\Model\Expand;
use Nails\Common\Helper\Model\Where;
use Nails\Common\Service\Asset;
use Nails\Common\Service\FormValidation;
use Nails\Common\Service\HttpCodes;
use Nails\Common\Service\Input;
use Nails\Common\Service\Session;
use Nails\Common\Service\Uri;
use Nails\Factory;

/**
 * Class Manager
 *
 * @package Nails\Cdn\Admin\Controller
 */
class Manager extends Base
{
    /**
     * Announces this controller's navGroups
     *
     * @return \stdClass
     */
    public static function announce()
    {
        if (userHasPermission(Permission\Object\Browse::class)) {
            /** @var Nav $oNavGroup */
            $oNavGroup = Factory::factory('Nav', \Nails\Admin\Constants::MODULE_SLUG);
            $oNavGroup
                ->setLabel('Media')
                ->setIcon('fa-images')
                ->addAction('Media Manager', 'index', [], 0);

            if (userHasPermission(Import::class)) {
                $oNavGroup
                    ->addAction('Import via URL', 'import');
            }

            return $oNavGroup;
        }

        return null;
    }

    // --------------------------------------------------------------------------

    /**
     * Browse CDN Objects
     *
     * @return void
     */
    public function index()
    {
        if (!userHasPermission(Permission\Object\Browse::class)) {
            unauthorised();
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Asset $oAsset */
        $oAsset = Factory::service('Asset');

        $this->data['sBucketSlug'] = $oInput->get('bucket');

        $oAsset
            ->library('KNOCKOUT')
            //  @todo (Pablo - 2018-12-01) - Update/Remove/Use minified once JS is refactored to be a module
            ->load('admin.mediamanager.js', Constants::MODULE_SLUG);

        $sBucketSlug      = $oInput->get('bucket');
        $sCallbackHandler = $oInput->get('CKEditor') ? 'ckeditor' : 'picker';

        $aCallback = $sCallbackHandler === 'ckeditor'
            ? [$oInput->get('CKEditorFuncNum')]
            : array_filter((array) $oInput->get('callback'));

        $oAsset->inline(
            'ko.applyBindings(
                new MediaManager(
                    "' . $sBucketSlug . '",
                    "' . $sCallbackHandler . '",
                    ' . json_encode($aCallback) . ',
                    ' . json_encode((bool) $oInput->get('isModal')) . '
                )
            );',
            'JS'
        );

        Helper::loadView('index');
    }

    // --------------------------------------------------------------------------

    /**
     * Routes import requests
     *
     * @throws CdnException
     * @throws FactoryException
     * @throws ModelException
     */
    public function import()
    {
        if (!userHasPermission(Permission\Object\Import::class)) {
            unauthorised();
        }

        /** @var Uri $oUri */
        $oUri = Factory::service('Uri');
        switch ($oUri->segment(5)) {
            case 'cancel':
                $this->importCancel((int) $oUri->segment(6));
                break;

            default:
                $this->importIndex();
                break;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * The main import UI
     *
     * @throws CdnException
     * @throws FactoryException
     * @throws ModelException
     */
    private function importIndex()
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var FormValidation $oFormValidation */
        $oFormValidation = Factory::service('FormValidation');
        /** @var Session $oSession */
        $oSession = Factory::service('Session');
        /** @var Bucket $oBucketModel */
        $oBucketModel = Factory::model('Bucket', Constants::MODULE_SLUG);
        /** @var Import $oImportModel */
        $oImportModel = Factory::model('ObjectImport', Constants::MODULE_SLUG);

        $aBuckets = $oBucketModel->getAllFlat([
            new Where('is_hidden', false),
        ]);

        if ($oInput->post()) {
            try {

                $oFormValidation
                    ->buildValidator([
                        'url'       => [
                            FormValidation::RULE_REQUIRED,
                            FormValidation::RULE_VALID_URL,
                            function ($sUrl) {

                                /** @var Head $oHttpRequest */
                                $oHttpRequest  = Factory::factory('HttpRequestHead');
                                $oHttpResponse = $oHttpRequest
                                    ->baseUri($sUrl)
                                    ->execute();

                                if ($oHttpResponse->getStatusCode() !== HttpCodes::STATUS_OK) {
                                    throw new ValidationException('Could not resolve URL, or URL is not public');
                                }
                            },
                        ],
                        'bucket_id' => [
                            FormValidation::RULE_REQUIRED,
                            FormValidation::rule(
                                FormValidation::RULE_IN_LIST,
                                implode(',', array_keys($aBuckets))
                            ),
                        ],
                    ])
                    ->run();

                /** @var Head $oHttpRequest */
                $oHttpRequest  = Factory::factory('HttpRequestHead');
                $oHttpResponse = $oHttpRequest
                    ->baseUri($oInput->post('url'))
                    ->execute();

                $iImportId = $oImportModel->create([
                    'url'       => $oInput->post('url'),
                    'bucket_id' => $oInput->post('bucket_id'),
                    'mime'      => $oHttpResponse->getHeader('Content-Type'),
                    'size'      => $oHttpResponse->getHeader('Content-Length'),
                ]);

                if (empty($iImportId)) {
                    throw new CdnException($oImportModel->lastError());
                }

                $oSession->setFlashData('import_accepted', true);
                redirect(self::url());

            } catch (ValidationException $e) {
                $this->oUserFeedback->error(sprintf(
                    'Failed to import file. %s',
                    $e->getMessage()
                ));
            }
        }

        $this
            ->setData('sMaxUploadSize', maxUploadSize())
            ->setData('aBuckets', $aBuckets)
            ->setData('bImportAccepted', (bool) $oSession->getFlashData('import_accepted'))
            ->setData('aImports', $oImportModel->getAll([
                new Expand('bucket'),
                'where' => [
                    [$oImportModel->getColumnCreatedBy(), activeUser('id')],
                    sprintf(
                        '%s >= DATE_SUB(NOW(), INTERVAL 24 HOUR)',
                        $oImportModel->getColumnCreated()
                    ),
                ],
            ]))
            ->setTitles(['Import via URL'])
            ->loadView('import');
    }

    // --------------------------------------------------------------------------

    /**
     * Handles cancelling an import
     *
     * @param int $iImportId The ID of the import to cancel
     *
     * @throws FactoryException
     */
    private function importCancel(int $iImportId)
    {
        /** @var Import $oImportModel */
        $oImportModel = Factory::model('ObjectImport', Constants::MODULE_SLUG);

        try {

            /** @var \Nails\Cdn\Resource\CdnObject\Import $oImport */
            $oImport = $oImportModel->getById($iImportId);

            if (empty($oImport)) {
                throw new NailsException('Invalid import ID.');

            } elseif ($oImport->status !== $oImportModel::STATUS_PENDING) {
                throw new NailsException('Import cannot be cancelled once it has begun.');
            }

            if (!$oImportModel->update($oImport->id, ['status' => $oImportModel::STATUS_CANCELLED])) {
                throw new NailsException($oImportModel->lastError());
            }

            $this->oUserFeedback->success('Import cancelled');

        } catch (\Exception $e) {
            $this->oUserFeedback->error('Failed to cancel import. ' . $e->getMessage());
        }

        redirect(self::url());
    }
}
