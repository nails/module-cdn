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
     * @return \stdClass
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

        $oInput = Factory::service('Input');
        $oCdn   = Factory::service('Cdn', 'nailsapp/module-cdn');

        if ($oInput->get('bucketId')) {
            $bucket = $oCdn->getBucket($oInput->get('bucketId'));
            if ($bucket) {
                $this->data['page']->title .= ' &rsaquo; ' . $bucket->label;
            }
        }

        // --------------------------------------------------------------------------

        //  Get pagination and search/sort variables
        $page      = $oInput->get('page')      ? $oInput->get('page')      : 0;
        $perPage   = $oInput->get('perPage')   ? $oInput->get('perPage')   : 50;
        $sortOn    = $oInput->get('sortOn')    ? $oInput->get('sortOn')    : 'o.created';
        $sortOrder = $oInput->get('sortOrder') ? $oInput->get('sortOrder') : 'desc';
        $keywords  = $oInput->get('keywords')  ? $oInput->get('keywords')  : '';

        // --------------------------------------------------------------------------

        //  Define the sortable columns
        $sortColumns = array(
            'o.id'               => 'Object ID',
            'o.filename_display' => 'Filename',
            'b.label'            => 'Bucket',
            'o.mime'             => 'File Type',
            'o.filesize'         => 'File Size',
            'o.created'          => 'Date Uploaded',
            'o.serves'           => 'Number of serves',
            'o.downloads'        => 'Number of downloads'
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

        if ($oInput->get('bucketId')) {

            $data['where'] = array(
                array('o.bucket_id', $oInput->get('bucketId'))
            );
        }

        // --------------------------------------------------------------------------

        //  Get the items for the page
        $totalRows             = $oCdn->countAllObjects($data);
        $this->data['objects'] = $oCdn->getObjects($page, $perPage, $data);

        //  Set Search and Pagination objects for the view
        $this->data['search']     = Helper::searchObject(true, $sortColumns, $sortOn, $sortOrder, $perPage, $keywords);
        $this->data['pagination'] = Helper::paginationObject($page, $perPage, $totalRows);

        //  Work out the return variable
        parse_str($oInput->server('QUERY_STRING'), $query);
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

        $oCdn                  = Factory::service('Cdn', 'nailsapp/module-cdn');
        $this->data['buckets'] = $oCdn->getBuckets();

        if (empty($this->data['buckets'])) {
            $oSession = Factory::service('Session', 'nailsapp/module-auth');
            $oSession->set_flashdata('warning', 'Create a bucket before uploading content.');
            redirect('admin/cdn/buckets/create');
        }

        // --------------------------------------------------------------------------

        $oAsset = Factory::service('Asset');
        $oAsset->load('admin.upload.min.js', 'nailsapp/module-cdn');
        $oAsset->load('dropzone/downloads/css/dropzone.css', 'NAILS-BOWER');
        $oAsset->load('dropzone/downloads/css/basic.css', 'NAILS-BOWER');
        $oAsset->load('dropzone/downloads/dropzone.min.js', 'NAILS-BOWER');
        $oAsset->inline('var _upload = new NAILS_Admin_CDN_Upload();', 'JS');

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

        $oUri     = Factory::service('Uri');
        $oInput   = Factory::service('Input');
        $oSession = Factory::service('Session', 'nailsapp/module-auth');
        $oCdn     = Factory::service('Cdn', 'nailsapp/module-cdn');

        $objectId = (int) $oUri->segment(5);
        $return   = $oInput->get('return') ? $oInput->get('return') : 'admin/cdn/objects/index';

        if ($oCdn->objectDelete($objectId)) {

            $status = 'success';
            $msg    = 'CDN Object was deleted successfully.';

        } else {

            $status = 'error';
            $msg    = 'CDN Object failed to delete. ' . $oCdn->lastError();
        }

        $oSession->set_flashdata($status, $msg);
        redirect($return);
    }
}
