<?php

/**
 * Manage the CDN Trash
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    AdminController
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Admin\Cdn;

use Nails\Admin\Helper;
use Nails\Factory;
use Nails\Cdn\Controller\BaseAdmin;

class Trash extends BaseAdmin
{
    /**
     * Returns an array of extra permissions for this controller
     * @return array
     */
    public static function permissions()
    {
        $permissions = parent::permissions();

        $permissions['browse']  = 'Can browse trash';
        $permissions['purge']   = 'Can empty trash';
        $permissions['restore'] = 'Can restore objects from the trash';

        return $permissions;
    }

    // --------------------------------------------------------------------------

    /**
     * Browse the CDN trash
     * @return void
     */
    public function index()
    {
        if (!userHasPermission('admin:cdn:trash:browse')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        //  Set method info
        $this->data['page']->title = 'Browse Trashed Objects';

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
            'o.created'          => 'Date Uploaded',
            'o.trashed'          => 'Date Trashed'
        );

        // --------------------------------------------------------------------------

        //  Define the $data variable for the queries
        $data = array(
            'sort' => array(
                array($sortOn, $sortOrder)
            ),
            'keywords' => $keywords
        );

        //  Get the items for the page
        $totalRows             = $this->cdn->countAllObjectsFromTrash($data);
        $this->data['objects'] = $this->cdn->getObjectsFromTrash($page, $perPage, $data);

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
        if (!empty($this->data['objects']) && userHasPermission('admin:cdn:trash:purge')) {

            Helper::addHeaderButton(
                'admin/cdn/trash/purge' . $return,
                'Empty Trash',
                'danger',
                'Are you sure?',
                'Emptying the trash will <strong>permanently</strong> delete all items.'
            );
        }

        // --------------------------------------------------------------------------

        Helper::loadView('index');
    }



    public function index2()
    {
        if (!userHasPermission('admin:cdn:trash:browse')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $this->data['page']->title = 'Browse Trashed Objects';

        // --------------------------------------------------------------------------

        //  Define the $_data variable, this'll be passed to the getAll() and countAll() methods
        $_data = array('where' => array(), 'sort' => array());

        // --------------------------------------------------------------------------

        //  Set useful vars
        $_page          = $this->input->get('page')     ? $this->input->get('page')     : 0;
        $_per_page      = $this->input->get('per_page') ? $this->input->get('per_page') : 25;
        $_sort_on       = $this->input->get('sort_on')  ? $this->input->get('sort_on')  : 'o.id';
        $_sort_order    = $this->input->get('order')    ? $this->input->get('order')    : 'desc';
        $_search        = $this->input->get('search')   ? $this->input->get('search')   : '';

        //  Set sort variables for view and for $_data
        $this->data['sort_on']    = $_data['sort']['column'] = $_sort_on;
        $this->data['sort_order'] = $_data['sort']['order']  = $_sort_order;
        $this->data['search']     = $_data['search']         = $_search;

        //  Define and populate the pagination object
        $this->data['pagination']             = new \stdClass();
        $this->data['pagination']->page       = $_page;
        $this->data['pagination']->per_page   = $_per_page;
        $this->data['pagination']->total_rows = $this->cdn->countAllObjectsFromTrash($_data);

        $this->data['objects'] = $this->cdn->getObjectsFromTrash($_page, $_per_page, $_data);

        // --------------------------------------------------------------------------

        $this->load->view('structure/header', $this->data);
        $this->load->view('admin/cdn/trash/browse', $this->data);
        $this->load->view('structure/footer', $this->data);
    }

    // --------------------------------------------------------------------------

    /**
     * Purge the CDN trash
     * @return void
     */
    public function purge()
    {
        if (!userHasPermission('admin:cdn:trash:purge')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        if ($this->input->get('ids')) {

            $purgeIds = explode(',', $this->input->get('ids'));
            $purgeIds = array_filter($purgeIds);
            $purgeIds = array_unique($purgeIds);

        } else {

            $purgeIds = null;
        }

        $return = $this->input->get('return') ? $this->input->get('return') : 'admin/cdn/trash/index';

        if ($this->cdn->purgeTrash($purgeIds)) {

            $status = 'success';

            if (!is_null($purgeIds) && count($purgeIds) == 1) {

                $msg = 'CDN Object was deleted successfully.';

            } elseif (!is_null($purgeIds) && count($purgeIds) > 1) {

                $msg = 'CDN Objects were deleted successfully.';

            } else {

                $msg = 'CDN Trash was emptied successfully.';
            }

        } else {

            $status = 'error';

            if (!is_null($purgeIds) && count($purgeIds) == 1) {

                $msg = 'CDN Object failed to delete. ' . $this->cdn->lastError();

            } elseif (!is_null($purgeIds) && count($purgeIds) > 1) {

                $msg = 'CDN Objects failed to delete. ' . $this->cdn->lastError();

            } else {

                $msg = 'CDN Trash failed to empty. ' . $this->cdn->lastError();
            }
        }

        $this->session->set_flashdata($status, $msg);
        redirect($return);
    }

    // --------------------------------------------------------------------------

    /**
     * Restore an item from the trash
     * @return void
     */
    public function restore()
    {
        if (!userHasPermission('admin:cdn:trash:restore')) {
            unauthorised();
        }

        // --------------------------------------------------------------------------

        $objectId = $this->uri->segment(5);
        $return   = $this->input->get('return') ? $this->input->get('return') : 'admin/cdn/trash/index';

        if ($this->cdn->objectRestore($objectId)) {

            $status = 'success';
            $msg    = 'CDN Object was restored successfully.';

        } else {

            $status = 'error';
            $msg    = 'CDN Object failed to restore. ' . $this->cdn->lastError();
        }

        $oSession = Factory::service('Session', 'nailsapp/module-auth');
        $oSession->set_flashdata($status, $msg);

        redirect($return);
    }
}
