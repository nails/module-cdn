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

class Buckets extends \AdminController
{
    /**
     * Announces this controller's navGroups
     * @return stdClass
     */
    public static function announce()
    {
        if (userHasPermission('admin.cdnadmin:0.can_browse_buckets')) {

            $navGroup = new \Nails\Admin\Nav('CDN');
            $navGroup->addMethod('Browse Buckets');
            return $navGroup;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Browse existing CDN Buckets
     * @return void
     */
    public function index()
    {
        if (!userHasPermission('admin.cdnadmin:0.can_browse_buckets')) {

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
        $totalRows             = $this->cdn->count_all_buckets($data);
        $this->data['buckets'] = $this->cdn->get_buckets($page, $perPage, $data);

        //  Set Search and Pagination objects for the view
        $this->data['search']     = \Nails\Admin\Helper::searchObject(true, $sortColumns, $sortOn, $sortOrder, $perPage, $keywords);
        $this->data['pagination'] = \Nails\Admin\Helper::paginationObject($page, $perPage, $totalRows);

        //  Work out the return variable
        parse_str($this->input->server('QUERY_STRING'), $query);
        $query = array_filter($query);
        $query = $query ? '?' . http_build_query($query) : '';
        $return = $query ? '?return=' . urlencode(uri_string() . $query) : '';
        $this->data['return'] = $return;

        //  Add a header button
        if (userHasPermission('admin.cdnadmin:0.can_create_buckets')) {

             \Nails\Admin\Helper::addHeaderButton('admin/cdn/buckets/create' . $return, 'Create Bucket');
        }

        // --------------------------------------------------------------------------

        \Nails\Admin\Helper::loadView('index');
    }

    // --------------------------------------------------------------------------

    /**
     * Create a new CDN Bucket
     * @return void
     */
    public function create()
    {
        if (!userHasPermission('admin.cdnadmin:0.can_create_buckets')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $return = $this->input->get('return') ? $this->input->get('return') : 'admin/cdn/buckets/index';
        $this->session->set_flashdata('message', '<strong>TODO:</strong> Manually create buckets from admin');
        redirect($return);
    }

    // --------------------------------------------------------------------------

    /**
     * Edit an existing CDN Bucket
     * @return void
     */
    public function edit()
    {
        if (!userHasPermission('admin.cdnadmin:0.can_edit_buckets')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $return = $this->input->get('return') ? $this->input->get('return') : 'admin/cdn/buckets/index';
        $this->session->set_flashdata('message', '<strong>TODO:</strong> Edit buckets from admin');
        redirect($return);
    }

    // --------------------------------------------------------------------------

    /**
     * Delete an existing CDN Bucket
     * @return void
     */
    public function delete()
    {
        if (!userHasPermission('admin.cdnadmin:0.can_delete_buckets')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $return = $this->input->get('return') ? $this->input->get('return') : 'admin/cdn/buckets/index';
        $this->session->set_flashdata('message', '<strong>TODO:</strong> Delete buckets from admin');
        redirect($return);
    }
}