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

class Trash extends \AdminController
{
    /**
     * Announces this controller's navGroups
     * @return stdClass
     */
    public static function announce()
    {
        if (userHasPermission('admin.cdnadmin:0.can_browse_trash')) {

            $navGroup = new \Nails\Admin\Nav('CDN');
            $navGroup->addMethod('Browse Trash');
            return $navGroup;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Browse the CDN trash
     * @return void
     */
    public function index()
    {
        if (!userHasPermission('admin.cdnadmin:0.can_browse_trash')) {

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
            'sort'  => array(
                'column' => $sortOn,
                'order'  => $sortOrder
            ),
            'keywords' => $keywords
        );

        //  Get the items for the page
        $totalRows             = $this->cdn->count_all_objects_from_trash($data);
        $this->data['objects'] = $this->cdn->get_objects_from_trash($page, $perPage, $data);

        //  Set Search and Pagination objects for the view
        $this->data['search']     = \Nails\Admin\Helper::searchObject($sortColumns, $sortOn, $sortOrder, $perPage, $keywords);
        $this->data['pagination'] = \Nails\Admin\Helper::paginationObject($page, $perPage, $totalRows);

        // --------------------------------------------------------------------------

        \Nails\Admin\Helper::loadView('index');
    }



    public function index2()
    {
        if (!userHasPermission('admin.cdnadmin:0.can_browse_trash')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $this->data['page']->title = 'Browse Trashed Objects';

        // --------------------------------------------------------------------------

        //  Define the $_data variable, this'll be passed to the get_all() and count_all() methods
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
        $this->data['pagination']->total_rows = $this->cdn->count_all_objects_from_trash($_data);

        $this->data['objects'] = $this->cdn->get_objects_from_trash($_page, $_per_page, $_data);

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
        if (!userHasPermission('admin.cdnadmin:0.can_purge_trash')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        if ($this->input->get('ids')) {

            $purgeIds = array();
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

                $msg = '<strong>Success!</strong> CDN Object was deleted successfully.';

            } elseif (!is_null($purgeIds) && count($purgeIds) > 1) {

                $msg = '<strong>Success!</strong> CDN Objects were deleted successfully.';

            } else {

                $msg = '<strong>Success!</strong> CDN Trash was emptied successfully.';
            }

        } else {

            $status = 'error';

            if (!is_null($purgeIds) && count($purgeIds) == 1) {

                $msg = '<strong>Sorry,</strong> CDN Object failed to delete. ' . $this->cdn->last_error();

            } elseif (!is_null($purgeIds) && count($purgeIds) > 1) {

                $msg = '<strong>Sorry,</strong> CDN Objects failed to delete. ' . $this->cdn->last_error();

            } else {

                $msg = '<strong>Sorry,</strong> CDN Trash failed to empty. ' . $this->cdn->last_error();
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
        if (!userHasPermission('admin.cdnadmin:0.can_restore_trash')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $objectId = $this->uri->segment(5);
        $return   = $this->input->get('return') ? $this->input->get('return') : 'admin/cdn/trash/index';

        if ($this->cdn->object_restore($objectId)) {

            $status = 'success';
            $msg    = '<strong>Success!</strong> CDN Object was restored successfully.';

        } else {

            $status = 'error';
            $msg    = '<strong>Sorry,</strong> CDN Object failed to restore. ' . $this->cdn->last_error();
        }

        $this->session->set_flashdata($status, $msg);
        redirect($return);
    }
}