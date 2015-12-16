<?php

/**
 * Manage CDN Objects
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

class Objects extends BaseAdmin
{
    /**
     * Announces this controller's navGroups
     * @return stdClass
     */
    public static function announce()
    {
        if (userHasPermission('admin:cdn:objects:browse')) {

            $oNavGroup = Factory::factory('Nav', 'nailsapp/module-admin');
            $oNavGroup->setLabel('CDN');
            $oNavGroup->setIcon('fa-cloud-upload');
            $oNavGroup->addAction('Browse Objects');
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

        $permissions['browse'] = 'Can browse objects';
        $permissions['create'] = 'Can create objects';
        $permissions['edit']   = 'Can edit objects';
        $permissions['delete'] = 'Can delete objects';

        return $permissions;
    }

    // --------------------------------------------------------------------------

    /**
     * Browse CDN Objects
     * @return void
     */
    public function index()
    {
        if (!userHasPermission('admin:cdn:objects:browse')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        //  Set method info
        $this->data['page']->title = 'Browse Objects';

        if ($this->input->get('bucketId')) {

            $bucket = $this->cdn->getBucket($this->input->get('bucketId'));

            if ($bucket) {

                $this->data['page']->title .= ' &rsaquo; ' . $bucket->label;
            }
        }

        // --------------------------------------------------------------------------

        //  Get pagination and search/sort variables
        $page      = $this->input->get('page')      ? $this->input->get('page')      : 0;
        $perPage   = $this->input->get('perPage')   ? $this->input->get('perPage')   : 50;
        $sortOn    = $this->input->get('sortOn')    ? $this->input->get('sortOn')    : 'o.id';
        $sortOrder = $this->input->get('sortOrder') ? $this->input->get('sortOrder') : 'desc';
        $keywords  = $this->input->get('keywords')  ? $this->input->get('keywords')  : '';

        // --------------------------------------------------------------------------

        //  Define the sortable columns
        $sortColumns = array(
            'o.id'               => 'Object ID',
            'o.filename_display' => 'Filename',
            'b.label'            => 'Bucket',
            'o.mime'             => 'File Type',
            'o.filesize'         => 'File Size',
            'o.created'          => 'Date Uploaded'
        );

        // --------------------------------------------------------------------------

        //  Define the $data variable for the queries
        $data = array(
            'sort' => array(
                array($sortOn, $sortOrder)
            ),
            'keywords' => $keywords
        );

        // --------------------------------------------------------------------------

        if ($this->input->get('bucketId')) {

            $data['where'] = array(
                array('o.bucket_id', $this->input->get('bucketId'))
            );
        }

        // --------------------------------------------------------------------------

        //  Get the items for the page
        $totalRows             = $this->cdn->countAllObjects($data);
        $this->data['objects'] = $this->cdn->getObjects($page, $perPage, $data);

        //  Set Search and Pagination objects for the view
        $this->data['search']     = Helper::searchObject(true, $sortColumns, $sortOn, $sortOrder, $perPage, $keywords);
        $this->data['pagination'] = Helper::paginationObject($page, $perPage, $totalRows);

        //  Work out the return variable
        parse_str($this->input->server('QUERY_STRING'), $query);
        $query = array_filter($query);
        $query = $query ? '?' . http_build_query($query) : '';
        $return = $query ? '?return=' . urlencode(uri_string() . $query) : '';
        $this->data['return'] = $return;

        //  Add header buttons
        if (userHasPermission('admin:cdn:objects:create')) {

             Helper::addHeaderButton('admin/cdn/objects/create' . $return, 'Upload Items');
        }

        if (userHasPermission('admin:cdn:trash:browse')) {

             Helper::addHeaderButton('admin/cdn/trash', 'Browse Trash', 'warning');
        }

        // --------------------------------------------------------------------------

        Helper::loadView('index');
    }

    // --------------------------------------------------------------------------

    /**
     * Create new CDN Objects
     * @return void
     */
    public function create()
    {
        if (!userHasPermission('admin:cdn:objects:create')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $this->data['page']->title = 'Upload Objects';

        // --------------------------------------------------------------------------

        $this->data['buckets'] = $this->cdn->getBuckets();

        if (empty($this->data['buckets'])) {
            $this->session->set_flashdata('warning', 'Create a bucket before uploading content.');
            redirect('admin/cdn/buckets/create');
        }

        // --------------------------------------------------------------------------

        $this->asset->load('admin.upload.min.js', 'nailsapp/module-cdn');
        $this->asset->load('dropzone/downloads/css/dropzone.css', 'NAILS-BOWER');
        $this->asset->load('dropzone/downloads/css/basic.css', 'NAILS-BOWER');
        $this->asset->load('dropzone/downloads/dropzone.min.js', 'NAILS-BOWER');
        $this->asset->inline('var _upload = new NAILS_Admin_CDN_Upload();', 'JS');

        // --------------------------------------------------------------------------

        Helper::loadView('create');
    }

    // --------------------------------------------------------------------------

    /**
     * Edit an existing CDN Object
     * @return void
     */
    public function edit()
    {
        if (!userHasPermission('admin:cdn:objects:edit')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $this->data['page']->title = 'Edit Object';

        // --------------------------------------------------------------------------

        Helper::loadView('edit');
    }

    // --------------------------------------------------------------------------

    /**
     * Delete an existing CDN object
     * @return void
     */
    public function delete()
    {
        if (!userHasPermission('admin:cdn:objects:delete')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $objectId = $this->uri->segment(5);
        $return   = $this->input->get('return') ? $this->input->get('return') : 'admin/cdn/objects/index';

        if ($this->cdn->objectDelete($objectId)) {

            $status = 'success';
            $msg    = 'CDN Object was deleted successfully.';

        } else {

            $status = 'error';
            $msg    = 'CDN Object failed to delete. ' . $this->cdn->lastError();
        }

        $this->session->set_flashdata($status, $msg);
        redirect($return);
    }
}
