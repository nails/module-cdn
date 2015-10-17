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

use Nails\Admin\Helper;
use Nails\Cdn\Controller\BaseAdmin;

class Buckets extends BaseAdmin
{
    /**
     * Announces this controller's navGroups
     * @return stdClass
     */
    public static function announce()
    {
        if (userHasPermission('admin:cdn:buckets:browse')) {

            $navGroup = new \Nails\Admin\Nav('CDN', 'fa-cloud-upload');
            $navGroup->addAction('Browse Buckets');
            return $navGroup;
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
        $totalRows             = $this->cdn->count_all_buckets($data);
        $this->data['buckets'] = $this->cdn->get_buckets($page, $perPage, $data);

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
        if (!userHasPermission('admin:cdn:buckets:edit')) {

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
        if (!userHasPermission('admin:cdn:buckets:delete')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $return = $this->input->get('return') ? $this->input->get('return') : 'admin/cdn/buckets/index';
        $this->session->set_flashdata('message', '<strong>TODO:</strong> Delete buckets from admin');
        redirect($return);
    }
}