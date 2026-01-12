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

namespace Nails\Admin\Cdn;

use Nails\Admin\Factory\Nav;
use Nails\Admin\Helper;
use Nails\Cdn\Constants;
use Nails\Cdn\Controller\BaseAdmin;
use Nails\Cdn\Exception\CdnException;
use Nails\Cdn\Model\Bucket;
use Nails\Cdn\Model\CdnObject\Import as ImportModel;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Exception\NailsException;
use Nails\Common\Exception\ValidationException;
use Nails\Common\Factory\HttpRequest\Head;
use Nails\Common\Helper\Model\Expand;
use Nails\Common\Helper\Model\Where;
use Nails\Common\Service\FormValidation;
use Nails\Common\Service\HttpCodes;
use Nails\Common\Service\Input;
use Nails\Common\Service\Session;
use Nails\Common\Service\Uri;
use Nails\Factory;

/**
 * Class Import
 *
 * @package Nails\Admin\Cdn
 */
class Import extends BaseAdmin
{
    /**
     * Announces this controller's navGroups
     */
    public static function announce(): Nav|array|null
    {
        if (userHasPermission('admin:cdn:mediamanager:object:browse')) {
            /** @var Nav $oNavGroup */
            $oNavGroup = Factory::factory('Nav', \Nails\Admin\Constants::MODULE_SLUG);
            $oNavGroup
                ->setLabel('Media')
                ->setIcon('fa-images')
                ->addAction('Import via URL', order: 999);
        }

        return $oNavGroup ?? null;
    }

    /**
     * Routes import requests
     *
     * @throws CdnException
     * @throws FactoryException
     * @throws ModelException
     */
    public function index(): void
    {
        if (!userHasPermission('admin:cdn:mediamanager:object:import')) {
            unauthorised();
        }

        /** @var Uri $oUri */
        $oUri = Factory::service('Uri');
        switch ($oUri->segment(5)) {
            case 'cancel':
                $this->cancel((int) $oUri->segment(6));
                break;

            default:
                $this->form();
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
    private function form(): void
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var FormValidation $oFormValidation */
        $oFormValidation = Factory::service('FormValidation');
        /** @var Session $oSession */
        $oSession = Factory::service('Session');
        /** @var Bucket $oBucketModel */
        $oBucketModel = Factory::model('Bucket', Constants::MODULE_SLUG);
        /** @var ImportModel $oImportModel */
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
                redirect('admin/cdn/import');

            } catch (ValidationException $e) {
                $this->oUserFeedback->error(sprintf(
                    'Failed to import file. %s',
                    $e->getMessage()
                ));
            }
        }

        $this->data['page']->title     = 'Import via URL';
        $this->data['sMaxUploadSize']  = maxUploadSize();
        $this->data['aBuckets']        = $aBuckets;
        $this->data['bImportAccepted'] = (bool) $oSession->getFlashData('import_accepted');
        $this->data['aImports']        = $oImportModel->getAll([
            new Expand('bucket'),
            'where' => [
                [$oImportModel->getColumnCreatedBy(), activeUser('id')],
                sprintf(
                    '%s >= DATE_SUB(NOW(), INTERVAL 24 HOUR)',
                    $oImportModel->getColumnCreated()
                ),
            ],
        ]);

        Helper::loadView('import');
    }

    // --------------------------------------------------------------------------

    /**
     * Handles cancelling an import
     *
     * @param int $iImportId The ID of the import to cancel
     *
     * @throws FactoryException
     */
    private function cancel(int $iImportId): void
    {
        /** @var ImportModel $oImportModel */
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

        redirect('admin/cdn/import');
    }
}
