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
        $oInput    = Factory::service('Input');
        $page      = $oInput->get('page')      ? $oInput->get('page')      : 0;
        $perPage   = $oInput->get('perPage')   ? $oInput->get('perPage')   : 50;
        $sortOn    = $oInput->get('sortOn')    ? $oInput->get('sortOn')    : 'o.id';
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
        $oCdn                  = Factory::service('Cdn', 'nailsapp/module-cdn');
        $totalRows             = $oCdn->countAllObjectsFromTrash($data);
        $this->data['objects'] = $oCdn->getObjectsFromTrash($page, $perPage, $data);

        //  Set Search and Pagination objects for the view
        $this->data['search']     = Helper::searchObject(true, $sortColumns, $sortOn, $sortOrder, $perPage, $keywords);
        $this->data['pagination'] = Helper::paginationObject($page, $perPage, $totalRows);

        //  Work out the return variable
        $oInput = Factory::service('Input');
        parse_str($oInput->server('QUERY_STRING'), $query);
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

        $oInput = Factory::service('Input');
        $oCdn   = Factory::service('Cdn', 'nailsapp/module-cdn');
        if ($oInput->get('ids')) {

            $purgeIds = explode(',', $oInput->get('ids'));
            $purgeIds = array_filter($purgeIds);
            $purgeIds = array_unique($purgeIds);

        } else {

            $purgeIds = null;
        }

        $return = $oInput->get('return') ? $oInput->get('return') : 'admin/cdn/trash/index';

        if ($oCdn->purgeTrash($purgeIds)) {

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

                $msg = 'CDN Object failed to delete. ' . $oCdn->lastError();

            } elseif (!is_null($purgeIds) && count($purgeIds) > 1) {

                $msg = 'CDN Objects failed to delete. ' . $oCdn->lastError();

            } else {

                $msg = 'CDN Trash failed to empty. ' . $oCdn->lastError();
            }
        }

        $oSession = Factory::service('Session', 'nailsapp/module-auth');
        $oSession->set_flashdata($status, $msg);

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

        $oCdn   = Factory::service('Cdn', 'nailsapp/module-cdn');
        $oInput = Factory::service('Input');
        $oUri   = Factory::service('Uri');
        $objectId = $oUri->segment(5);
        $return   = $oInput->get('return') ? $oInput->get('return') : 'admin/cdn/trash/index';

        if ($oCdn->objectRestore($objectId)) {

            $status = 'success';
            $msg    = 'CDN Object was restored successfully.';

        } else {

            $status = 'error';
            $msg    = 'CDN Object failed to restore. ' . $oCdn->lastError();
        }

        $oSession = Factory::service('Session', 'nailsapp/module-auth');
        $oSession->set_flashdata($status, $msg);

        redirect($return);
    }
}
