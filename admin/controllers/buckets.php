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

use Nails\Factory;
use Nails\Admin\Helper;
use Nails\Cdn\Controller\BaseAdmin;

class Buckets extends BaseAdmin
{
    /**
     * The base URL for this controller
     */
    const CONTROLLER_URL = 'admin/cdn/buckets';

    // --------------------------------------------------------------------------

    /**
     * Announces this controller's navGroups
     * @return \stdClass
     */
    public static function announce()
        {
        if (userHasPermission('admin:cdn:buckets:browse')) {

            $oNavGroup = Factory::factory('Nav', 'nailsapp/module-admin');
            $oNavGroup->setLabel('CDN');
            $oNavGroup->setIcon('fa-cloud-upload');
            $oNavGroup->addAction('Browse Buckets');
            return $oNavGroup;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of extra permissions for this controller
     * @return array
     */
    public static function permissions()
    {
        $permissions = parent::permissions();

        $permissions['browse'] = 'Can browse buckets';
        $permissions['create'] = 'Can create objects';
        $permissions['edit']   = 'Can edit objects';
        $permissions['delete'] = 'Can delete objects';

        return $permissions;
    }

    // --------------------------------------------------------------------------

    /**
     * Browse existing CDN Buckets
     * @return void
     */
    public function index()
    {
        if (!userHasPermission('admin:cdn:buckets:browse')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        //  Set method info
        $this->data['page']->title = 'Browse Buckets';

        // --------------------------------------------------------------------------

        //  Get pagination and search/sort variables
        $page      = $this->input->get('page')      ? $this->input->get('page')      : 0;
        $perPage   = $this->input->get('perPage')   ? $this->input->get('perPage')   : 50;
        $sortOn    = $this->input->get('sortOn')    ? $this->input->get('sortOn')    : 'b.label';
        $sortOrder = $this->input->get('sortOrder') ? $this->input->get('sortOrder') : 'asc';
        $keywords  = $this->input->get('keywords')  ? $this->input->get('keywords')  : '';

        // --------------------------------------------------------------------------

        //  Define the sortable columns
        $sortColumns = array(
            'b.id'    => 'Bucket ID',
            'b.label' => 'Label'
        );

        // --------------------------------------------------------------------------

        //  Define the $data variable for the queries
        $data = array(
            'sort' => array(
                array($sortOn, $sortOrder)
            ),
            'keywords' => $keywords,
            'includeObjectCount' => true
        );

        //  Get the items for the page
        $totalRows             = $this->cdn->countAllBuckets($data);
        $this->data['buckets'] = $this->cdn->getBuckets($page, $perPage, $data);

        //  Set Search and Pagination objects for the view
        $this->data['search']     = Helper::searchObject(true, $sortColumns, $sortOn, $sortOrder, $perPage, $keywords);
        $this->data['pagination'] = Helper::paginationObject($page, $perPage, $totalRows);

        //  Work out the return variable
        parse_str($this->input->server('QUERY_STRING'), $query);
        $query = array_filter($query);
        $query = $query ? '?' . http_build_query($query) : '';
        $return = $query ? '?return=' . urlencode(uri_string() . $query) : '';
        $this->data['return'] = $return;

        //  Add a header button
        if (userHasPermission('admin:cdn:buckets:create')) {

             Helper::addHeaderButton('admin/cdn/buckets/create' . $return, 'Create Bucket');
        }

        // --------------------------------------------------------------------------

        Helper::loadView('index');
    }

    // --------------------------------------------------------------------------

    /**
     * Create a new CDN Bucket
     * @return void
     */
    public function create()
    {
        if (!userHasPermission('admin:cdn:buckets:create')) {
            unauthorised();
        }

        $oInput     = Factory::service('Input');
        $oDb        = Factory::service('Database');
        $oItemModel = Factory::model('Bucket', 'nailsapp/module-cdn');

        if ($oInput->post()) {
            if ($this->validatePost()) {
                try {
                    $oDb->trans_begin();
                    if (!$oItemModel->create($this->extractPost())) {
                        throw new \Exception('Failed to create item.' . $oItemModel->lastError());
                    }

                    $oDb->trans_commit();
                    $oSession = Factory::service('Session', 'nailsapp/module-auth');
                    $oSession->set_flashdata('success', 'Bucket created successfully.');
                    redirect(self::CONTROLLER_URL);

                } catch (\Exception $e) {
                    $oDb->trans_rollback();
                    $this->data['error'] = $e->getMessage();
                }

            } else {
                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        //  View Data & Assets
        $this->loadViewData();

        $this->data['page']->title = 'Bucket &rsaquo; Create';
        Helper::loadView('edit');
    }

    // --------------------------------------------------------------------------

    /**
     * Edit an existing item
     * @return void
     */
    public function edit()
    {
        if (!userHasPermission('admin:cdn:buckets:edit')) {
            unauthorised();
        }

        $oInput     = Factory::service('Input');
        $oDb        = Factory::service('Database');
        $oUri       = Factory::service('Uri');
        $oItemModel = Factory::model('Bucket', 'nailsapp/module-cdn');
        $iItemId    = (int) $oUri->segment(5);
        $oItem      = $oItemModel->getById($iItemId);

        if (empty($oItem)) {
            show_404();
        }

        // --------------------------------------------------------------------------

        if ($oInput->post()) {
            if ($this->validatePost()) {
                try {
                    $oDb->trans_begin();
                    if (!$oItemModel->update($iItemId, $this->extractPost())) {
                        throw new \Exception('Failed to update item.' . $oItemModel->lastError());
                    }

                    $oDb->trans_commit();
                    $oSession = Factory::service('Session', 'nailsapp/module-auth');
                    $oSession->set_flashdata('success', 'Bucket updated successfully.');
                    redirect(self::CONTROLLER_URL);

                } catch (\Exception $e) {
                    $oDb->trans_rollback();
                    $this->data['error'] = $e->getMessage();
                }

            } else {
                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        //  View Data & Assets
        $this->loadViewData($oItem);

        $this->data['page']->title = 'Bucket &rsaquo; Edit';
        Helper::loadView('edit');
    }

    // --------------------------------------------------------------------------

    /**
     * Form validation for edit/create
     * @return boolean
     */
    private function validatePost()
    {
        $oFormValidation = Factory::service('FormValidation');
        $oItemModel      = Factory::model('Bucket', 'nailsapp/module-cdn');
        $sBucketTable    =  $oItemModel->getTableName();

        $aRules = array(
            'label'         => 'required|is_unique[' . $sBucketTable . '.label]',
            'allowed_types' => '',
            'max_size'      => 'is_natural_no_zero',
            'disk_quota'    => 'is_natural_no_zero',
        );

        $aRulesFormValidation = array();
        foreach ($aRules as $sKey => $sRules) {
            $aRulesFormValidation[] = array(
                'field' => $sKey,
                'label' => '',
                'rules' => $sRules
            );
        }

        $oFormValidation->set_rules($aRulesFormValidation);

        $oFormValidation->set_message('required', lang('fv_required'));
        $oFormValidation->set_message('is_unique', lang('fv_is_unique'));
        $oFormValidation->set_message('is_natural_no_zero', lang('fv_is_natural_no_zero'));

        return $oFormValidation->run();
    }

    // --------------------------------------------------------------------------

    /**
     * Load data for the edit/create view
     * @param  \stdClass $oItem The main item object
     * @return void
     */
    private function loadViewData($oItem = null)
    {
        $this->data['item'] = $oItem;
    }

    // --------------------------------------------------------------------------

    /**
     * Extract data from post variable
     * @return array
     */
    private function extractPost()
    {
        $oInput = Factory::service('Input');
        return array(
            'label'         => $oInput->post('label'),
            'allowed_types' => $oInput->post('allowed_types'),
            'max_size'      => (int) $oInput->post('max_size') ?: null,
            'disk_quota'    => (int) $oInput->post('disk_quota') ?: null,
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Delete a bucket
     * @return void
     */
    public function delete()
    {
        $oItemModel = Factory::model('Bucket', 'nailsapp/module-cdn');
        $oUri       = Factory::service('Uri');
        $oDb        = Factory::service('Database');
        $iItemId    = (int) $oUri->segment(5);
        $oItem      = $oItemModel->getById($iItemId);

        if (empty($oItem)) {
            show_404();
        }

        try {
            if (!$oItemModel->delete($iItemId)) {
                throw new \Exception('Failed to delete item.' . $oItemModel->lastError());
            }

            $oDb->trans_commit();
            $oSession = Factory::service('Session', 'nailsapp/module-auth');
            $oSession->set_flashdata(
                'success',
                'Item deleted successfully.'
            );
            redirect(self::CONTROLLER_URL);

        } catch (\Exception $e) {
            $oDb->trans_rollback();
            $this->data['error'] = $e->getMessage();
        }
    }
}
