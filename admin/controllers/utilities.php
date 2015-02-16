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

class Utilities extends \AdminController
{
    /**
     * Announces this controller's navGroups
     * @return stdClass
     */
    public static function announce()
    {
        $navGroup = new \Nails\Admin\Nav('Utilities');
        $navGroup->addMethod('CDN: Find orphaned objects');

        return $navGroup;
    }

    // --------------------------------------------------------------------------

    /**
     * Find orphaned CDN objects
     * @return void
     */
    public function index()
    {
        if ($this->input->is_cli_request()) {

            return $this->indexCli();
        }

        // --------------------------------------------------------------------------

        if ($this->input->post()) {

            //  A little form validation
            $type   = $this->input->post('type');
            $parser = $this->input->post('parser');
            $error  = '';

            if ($type == 'db' && $parser == 'create') {

                $error  = 'Cannot use "Add to database" results parser when finding orphaned database objects.';
            }


            if (empty($error)) {

                switch ($type) {

                    case 'db':

                        $this->data['orphans']  = $this->cdn->find_orphaned_objects();
                        break;

                    //  @TODO
                    case 'file':

                        $this->data['message']  = '<strong>TODO:</strong> find orphaned files.';
                        break;

                    //  Invalid request
                    default:

                        $this->data['error']    = 'Invalid search type.';
                        break;
                }

                if (isset($this->data['orphans'])) {

                    switch ($parser) {

                        case 'list':

                            $this->data['success'] = '<strong>Search complete!</strong> your results are show below.';
                            break;

                        //  TODO: keep the unset(), it prevents the table from rendering
                        case 'purge':

                            $this->data['message'] = '<strong>TODO:</strong> purge results.'; unset($this->data['orphans']);
                            break;

                        case 'create':

                            $this->data['message'] = '<strong>TODO:</strong> create objects using results.'; unset($this->data['orphans']);
                            break;

                        //  Invalid request
                        default:

                            $this->data['error'] = 'Invalid result parse selected.'; unset($this->data['orphans']);
                            break;
                    }
                }

            } else {

                $this->data['error'] = 'An error occurred. ' . $error;
            }
        }

        // --------------------------------------------------------------------------

        $this->data['page']->title = 'CDN: Find Orphaned Objects';

        // --------------------------------------------------------------------------

        $this->asset->load('nails.admin.utilities.cdn.orphans.min.js', true);

        // --------------------------------------------------------------------------

        \Nails\Admin\Helper::loadView('index');
    }

    // --------------------------------------------------------------------------

    /**
     * Find orphaned CDN objects (command line)
     * @return void
     */
    protected function indexCli()
    {
        //  @TODO: Complete CLI functionality for report generating
        echo 'Sorry, this functionality is not complete yet. If you are experiencing timeouts please increase the timeout limit for PHP.';
    }
}
