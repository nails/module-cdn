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

namespace Nails\Cdn\Admin\Controller;

use Nails\Admin\Controller\Base;
use Nails\Admin\Helper;
use Nails\Cdn\Admin\Permission;
use Nails\Cdn\Constants;
use Nails\Factory;

/**
 * Class Utilities
 *
 * @package Nails\Cdn\Admin\Controller
 */
class Utilities extends Base
{
    /**
     * Announces this controller's navGroups
     *
     * @return \stdClass
     */
    public static function announce()
    {
        $oNavGroup = Factory::factory('Nav', \Nails\Admin\Constants::MODULE_SLUG);
        $oNavGroup->setLabel('Utilities');

        if (userHasPermission(Permission\Object\FindOrphan::class)) {
            $oNavGroup->addAction('CDN: Find orphaned objects');
        }

        return $oNavGroup;
    }

    // --------------------------------------------------------------------------

    /**
     * Find orphaned CDN objects
     *
     * @return void
     */
    public function index()
    {
        if (!userHasPermission(Permission\Object\FindOrphan::class)) {
            unauthorised();
        }

        // --------------------------------------------------------------------------

        /** @var \Nails\Common\Service\Input $oInput */
        $oInput = Factory::service('Input');

        if ($oInput::isCli()) {
            $this->indexCli();
        } else {

            if ($oInput->post()) {

                //  A little form validation
                $type   = $oInput->post('type');
                $parser = $oInput->post('parser');
                $error  = '';

                if ($type == 'db' && $parser == 'create') {
                    $error = 'Cannot use "Add to database" results parser when finding orphaned database objects.';
                }

                if (empty($error)) {

                    switch ($type) {

                        case 'db':

                            $oCdn                  = Factory::service('Cdn', Constants::MODULE_SLUG);
                            $this->data['orphans'] = $oCdn->findOrphanedObjects();
                            break;

                        //  @TODO
                        case 'file':
                            $this->oUserFeedback->warning('<strong>TODO:</strong> find orphaned files.');
                            break;

                        //  Invalid request
                        default:

                            $this->oUserFeedback->error('Invalid search type.');
                            break;
                    }

                    if (isset($this->data['orphans'])) {

                        switch ($parser) {

                            case 'list':
                                $this->oUserFeedback->success('<strong>Search complete!</strong> Your results are show below.');
                                break;

                            //  @todo: keep the unset(), it prevents the table from rendering
                            case 'purge':
                                $this->oUserFeedback->warning('<strong>TODO:</strong> purge results.');
                                unset($this->data['orphans']);
                                break;

                            case 'create':
                                $this->oUserFeedback->warning('<strong>TODO:</strong> create objects using results.');
                                unset($this->data['orphans']);
                                break;

                            //  Invalid request
                            default:

                                $this->oUserFeedback->error('Invalid result parse selected.');
                                unset($this->data['orphans']);
                                break;
                        }
                    }

                } else {
                    $this->oUserFeedback->error('An error occurred. ' . $error);
                }
            }

            // --------------------------------------------------------------------------

            $this
                ->setTitles(['CDN', 'Find Orphaned Objects'])
                ->loadView('index');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Find orphaned CDN objects (command line)
     *
     * @return void
     */
    protected function indexCli()
    {
        //  @TODO: Complete CLI functionality for report generating
        echo 'Sorry, this functionality is not complete yet. If you are experiencing timeouts please increase the timeout limit for PHP.';
    }
}
