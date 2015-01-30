<?php

//  Include NAILS_Admin_Controller; executes common admin functionality.
require_once '_admin.php';

/**
 * Manage the CDN
 *
 * @package     Nails
 * @subpackage  module-admin
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

class NAILS_Cdnadmin extends NAILS_Admin_Controller
{
    /**
     * Announces this controller's details
     * @return stdClass
     */
    public static function announce()
    {
        $d = new stdClass();

        // --------------------------------------------------------------------------

        //  Configurations
        $d->name = 'CDN';
        $d->icon = 'fa-cloud-upload';

        // --------------------------------------------------------------------------

        //  Navigation options
        if (user_has_permission('admin.cdnadmin:0.can_browse_buckets')) {

            $d->funcs['bucket'] = 'Browse Buckets';
        }

        if (user_has_permission('admin.cdnadmin:0.can_browse_objects')) {

            $d->funcs['object'] = 'Browse Objects';
        }

        if (user_has_permission('admin.cdnadmin:0.can_browse_trash')) {

            $d->funcs['trash'] = 'Browse Trash';
        }

        // --------------------------------------------------------------------------

        return $d;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of extra permissions for this controller
     * @param  string $classIndex The class_index value, used when multiple admin instances are available
     * @return array
     */
    public static function permissions($classIndex = null)
    {
        $permissions = parent::permissions($classIndex);

        // --------------------------------------------------------------------------

        //  Buket permissions
        $permissions['can_browse_buckets'] = 'Can browse buckets';
        $permissions['can_create_buckets'] = 'Can create objects';
        $permissions['can_edit_buckets']   = 'Can edit objects';
        $permissions['can_delete_buckets'] = 'Can delete objects';

        //  Object Permissions
        $permissions['can_browse_objects'] = 'Can browse objects';
        $permissions['can_create_objects'] = 'Can create objects';
        $permissions['can_edit_objects']   = 'Can edit objects';
        $permissions['can_delete_objects'] = 'Can delete objects';

        //  Trash Permissions
        $permissions['can_browse_trash']   = 'Can browse trash';
        $permissions['can_purge_trash']    = 'Can empty trash';
        $permissions['can_restore_trash']  = 'Can restore objects from the trash';

        // --------------------------------------------------------------------------

        return $permissions;
    }

    // --------------------------------------------------------------------------

    /**
     * Routes requests to bucket methods
     * @return void
     */
    public function bucket()
    {
        $this->routeMethod('bucket');
    }

    // --------------------------------------------------------------------------

    /**
     * Browse existing CDN Buckets
     * @return void
     */
    protected function bucketBrowse()
    {
        if (!user_has_permission('admin.cdnadmin:0.can_browse_buckets')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $this->data['page']->title = 'Browse Buckets';

        // --------------------------------------------------------------------------

        $this->load->view('structure/header', $this->data);
        $this->load->view('admin/cdn/bucket/browse', $this->data);
        $this->load->view('structure/footer', $this->data);
    }

    // --------------------------------------------------------------------------

    /**
     * Create a new CDN Bucket
     * @return void
     */
    protected function bucketCreate()
    {
        if (!user_has_permission('admin.cdnadmin:0.can_create_buckets')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $_return = $this->input->get('return') ? $this->input->get('return') : 'admin/cdnadmin/bucket/browse';
        $this->session->set_flashdata('message', '<strong>TODO:</strong> Manually create buckets from admin');
        redirect($_return);
    }

    // --------------------------------------------------------------------------

    /**
     * Edit an existing CDN Bucket
     * @return void
     */
    protected function bucketEdit()
    {
        if (!user_has_permission('admin.cdnadmin:0.can_edit_buckets')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $_return = $this->input->get('return') ? $this->input->get('return') : 'admin/cdnadmin/bucket/browse';
        $this->session->set_flashdata('message', '<strong>TODO:</strong> Edit buckets from admin');
        redirect($_return);
    }

    // --------------------------------------------------------------------------

    /**
     * Delete an existing CDN Bucket
     * @return void
     */
    protected function bucketDelete()
    {
        if (!user_has_permission('admin.cdnadmin:0.can_delete_buckets')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $_return = $this->input->get('return') ? $this->input->get('return') : 'admin/cdnadmin/bucket/browse';
        $this->session->set_flashdata('message', '<strong>TODO:</strong> Delete buckets from admin');
        redirect($_return);
    }

    // --------------------------------------------------------------------------

    /**
     * Routes requests to object methods
     * @return void
     */
    public function object()
    {
        $this->routeMethod('object');
    }

    // --------------------------------------------------------------------------

    /**
     * Browse CDN Objects
     * @return void
     */
    protected function objectBrowse()
    {
        if (!user_has_permission('admin.cdnadmin:0.can_browse_objects')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $this->data['page']->title = 'Browse Objects';

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
        $this->data['pagination']             = new stdClass();
        $this->data['pagination']->page       = $_page;
        $this->data['pagination']->per_page   = $_per_page;
        $this->data['pagination']->total_rows = $this->cdn->count_all_objects($_data);

        $this->data['objects'] = $this->cdn->get_objects($_page, $_per_page, $_data);

        // --------------------------------------------------------------------------

        $this->load->view('structure/header', $this->data);
        $this->load->view('admin/cdn/object/browse', $this->data);
        $this->load->view('structure/footer', $this->data);
    }

    // --------------------------------------------------------------------------

    /**
     * Create new CDN Objects
     * @return void
     */
    protected function objectCreate()
    {
        if (!user_has_permission('admin.cdnadmin:0.can_create_objects')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $this->data['page']->title = 'Upload Objects';

        // --------------------------------------------------------------------------

        $this->data['buckets'] = $this->cdn->get_buckets();

        // --------------------------------------------------------------------------

        if ($this->input->get('is_fancybox')) {

            $this->data['headerOverride'] = 'structure/headerBlank';
            $this->data['footerOverride'] = 'structure/headerBlank';
        }

        // --------------------------------------------------------------------------

        $this->asset->load('nails.admin.cdn.upload.min.js', 'NAILS');
        $this->asset->load('dropzone/downloads/css/dropzone.css', 'NAILS-BOWER');
        $this->asset->load('dropzone/downloads/css/basic.css', 'NAILS-BOWER');
        $this->asset->load('dropzone/downloads/dropzone.min.js', 'NAILS-BOWER');
        $this->asset->inline('var _upload = new NAILS_Admin_CDN_Upload();', 'JS');

        // --------------------------------------------------------------------------

        $this->load->view('structure/header', $this->data);
        $this->load->view('admin/cdn/object/create', $this->data);
        $this->load->view('structure/footer', $this->data);
    }

    // --------------------------------------------------------------------------

    /**
     * Edit an existing CDN Object
     * @return void
     */
    protected function objectEdit()
    {
        if (!user_has_permission('admin.cdnadmin:0.can_edit_objects')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $this->data['page']->title = 'Edit Object';

        // --------------------------------------------------------------------------

        $this->load->view('structure/header', $this->data);
        $this->load->view('admin/cdn/object/edit', $this->data);
        $this->load->view('structure/footer', $this->data);
    }

    // --------------------------------------------------------------------------

    /**
     * Delete an existing CDN object
     * @return void
     */
    protected function objectDelete()
    {
        if (!user_has_permission('admin.cdnadmin:0.can_delete_objects')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $objectId = $this->uri->segment(5);
        $return   = $this->input->get('return') ? $this->input->get('return') : 'admin/cdnadmin/object/browse';

        if ($this->cdn->object_delete($objectId)) {

            $status = 'success';
            $msg    = '<strong>Success!</strong> CDN Object was deleted successfully.';

        } else {

            $status = 'error';
            $msg    = '<strong>Sorry,</strong> CDN Object failed to delete. ' . $this->cdn->last_error();
        }

        $this->session->set_flashdata($status, $msg);
        redirect($return);
    }

    // --------------------------------------------------------------------------

    /**
     * Routes requests to trash methods
     * @return void
     */
    public function trash()
    {
        $this->routeMethod('trash');
    }

    // --------------------------------------------------------------------------

    /**
     * Browse the CDN trash
     * @return void
     */
    protected function trashBrowse()
    {
        if (!user_has_permission('admin.cdnadmin:0.can_browse_trash')) {

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
        $this->data['pagination']             = new stdClass();
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
    protected function trashPurge()
    {
        if (!user_has_permission('admin.cdnadmin:0.can_purge_trash')) {

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

        $return = $this->input->get('return') ? $this->input->get('return') : 'admin/cdnadmin/trash';

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
    protected function trashRestore()
    {
        if (!user_has_permission('admin.cdnadmin:0.can_restore_trash')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $objectId = $this->uri->segment(5);
        $return   = $this->input->get('return') ? $this->input->get('return') : 'admin/cdnadmin/trash/browse';

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

    // --------------------------------------------------------------------------

    /**
     * Calls methods based on a specific method prefix
     * @param  string $prefix The prefix to add
     * @return void
     */
    protected function routeMethod($prefix = '')
    {
        $this->load->helper('string');

        $method = $this->uri->segment(4) ? $this->uri->segment(4) : 'browse';
        $method = $prefix . underscore_to_camelcase($method, false);

        if (method_exists($this, $method)) {

            $this->$method();

        } else {

            show_404();
        }
    }
}

// --------------------------------------------------------------------------

/**
 * OVERLOADING NAILS' ADMIN MODULES
 *
 * The following block of code makes it simple to extend one of the core admin
 * controllers. Some might argue it's a little hacky but it's a simple 'fix'
 * which negates the need to massively extend the CodeIgniter Loader class
 * even further (in all honesty I just can't face understanding the whole
 * Loader class well enough to change it 'properly').
 *
 * Here's how it works:
 *
 * CodeIgniter instantiate a class with the same name as the file, therefore
 * when we try to extend the parent class we get 'cannot redeclare class X' errors
 * and if we call our overloading class something else it will never get instantiated.
 *
 * We solve this by prefixing the main class with NAILS_ and then conditionally
 * declaring this helper class below; the helper gets instantiated et voila.
 *
 * If/when we want to extend the main class we simply define NAILS_ALLOW_EXTENSION_CLASSNAME
 * before including this PHP file and extend as normal (i.e in the same way as below);
 * the helper won't be declared so we can declare our own one, app specific.
 *
 **/

if (!defined('NAILS_ALLOW_EXTENSION_CDN')) {

    /**
     * Proxy class for NAILS_Cdnadmin
     */
    class Cdnadmin extends NAILS_Cdnadmin
    {
    }
}
