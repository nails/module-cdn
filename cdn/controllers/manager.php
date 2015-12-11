<?php

/**
 * This class provides the CDN manager, a one stop shop for managing objects in buckets
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

use Nails\Factory;
use Nails\Cdn\Controller\Base;

class Manager extends Base
{
    protected $oCdn;

    // --------------------------------------------------------------------------

    /**
     * Construct the manage, check user is permitted to browse this bucket, etc
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  Determine if browsing/uploading is permitted
        $this->data['enabled'] = $this->user_model->isLoggedIn() ? true : false;
        $this->data['enabled'] = true;

        // --------------------------------------------------------------------------

        //  Load CDN library
        $this->oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');

        // --------------------------------------------------------------------------

        if ($this->data['enabled']) {

            /**
             * Define the directory, if a bucket has been specified use that, if not
             * then use the user's upload directory
             */

            if ($this->input->get('bucket') && $this->input->get('hash')) {

                /**
                 * Decrypt the bucket and cross reference with the hash. Doing this so
                 * that users can't casually specify a bucket and upload willy nilly.
                 */

                $bucket    = $this->input->get('bucket');
                $hash      = $this->input->get('hash');
                $decrypted = $this->encrypt->decode($bucket, APP_PRIVATE_KEY);

                if ($decrypted) {

                    $bucket = explode('|', $decrypted);

                    if ($bucket[0] && isset($bucket[1])) {

                        //  Bucket and nonce set, cross-check
                        if (md5($bucket[0] . '|' . $bucket[1] . '|' . APP_PRIVATE_KEY) === $hash) {

                            $this->data['bucket'] = $this->oCdn->getBucket($bucket[0]);

                            if ($this->data['bucket']) {

                                $testOk = true;

                            } else {

                                //  Bucket doesn't exist - attempt to create it
                                if ($this->oCdn->bucketCreate($bucket[0])) {

                                    $testOk = true;
                                    $this->data['bucket'] = $this->oCdn->getBucket($bucket[0]);

                                } else {

                                    $testOk = false;
                                    $error  = 'Bucket <strong>"' . $bucket[0] . '"</strong> does not exist';
                                    $error .= '<small>Additionally, the following error occured while attempting ';
                                    $error .= 'to create the bucket:<br />' . $this->oCdn->lastError() . '</small>';
                                }
                            }

                        } else {

                            $testOk = false;
                            $error  = 'Could not verify bucket hash';
                        }

                    } else {

                        $testOk = false;
                        $error  = 'Incomplete bucket hash';
                    }

                } else {

                    $testOk = false;
                    $error  = 'Could not decrypt bucket hash';
                }

                // --------------------------------------------------------------------------

                if (!$testOk) {

                    $this->data['enabled']   = false;
                    $this->data['badBucket'] = $error;
                }

            } else {

                //  No bucket specified, use the user's upload bucket
                $slug  = 'user-' . activeUser('id');
                $label = 'User Upload Directory';

                // --------------------------------------------------------------------------

                //  Test bucket, if it doesn't exist, create it
                $this->data['bucket'] = $this->oCdn->getBucket($slug);

                if (!$this->data['bucket']) {

                    $bucket_id = $this->oCdn->bucketCreate($slug, $label);

                    if (!$bucket_id) {

                         $this->data['enabled']    = false;
                         $this->data['badBucket'] = 'Unable to create upload bucket: ' . $this->oCdn->lastError();

                    } else {

                        $this->data['bucket'] = $this->oCdn->getBucket($bucket_id);
                    }
                }
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Renders the media manager
     * @return void
     */
    public function browse()
    {
        //  Unload all styles and load just the nails styles
        $this->asset->clear();
        $this->asset->load('nails.cdn.manager.css', true);

        //  Fetch files
        if ($this->data['enabled']) {

            //  Load Bower assets
            $this->asset->load('jquery/dist/jquery.min.js', 'NAILS-BOWER');
            $this->asset->load('fancybox/source/jquery.fancybox.pack.js', 'NAILS-BOWER');
            $this->asset->load('fancybox/source/jquery.fancybox.css', 'NAILS-BOWER');
            $this->asset->load('jquery.scrollTo/jquery.scrollTo.min.js', 'NAILS-BOWER');
            $this->asset->load('tipsy/src/javascripts/jquery.tipsy.js', 'NAILS-BOWER');
            $this->asset->load('tipsy/src/stylesheets/tipsy.css', 'NAILS-BOWER');
            $this->asset->library('MUSTACHE');
            $this->asset->load('jquery-cookie/jquery.cookie.js', 'NAILS-BOWER');
            // $this->asset->load('dropzone/downloads/dropzone.min.js', 'NAILS-BOWER');
            $this->asset->load('fontawesome/css/font-awesome.min.css', 'NAILS-BOWER');

            //  Load other assets
            $this->asset->load('nails.default.min.js', true);
            $this->asset->load('nails.api.min.js', true);
            $this->asset->load('nails.cdn.manager.min.js', true);

            // --------------------------------------------------------------------------

            //  List the bucket objects
            $this->data['objects'] = $this->oCdn->bucketList($this->data['bucket']->id);

            // --------------------------------------------------------------------------

            $this->load->view('manager/browse', $this->data);

        } else {

            $this->load->view('manager/disabled', $this->data);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Upload an object
     * @return void
     */
    public function upload()
    {
        //  Returning to...?
        $return = site_url('cdn/manager/browse', isPageSecure());
        $return .= $this->input->server('QUERY_STRING') ? '?' . $this->input->server('QUERY_STRING') : '';

        // --------------------------------------------------------------------------

        //  User is authorised to upload?
        if (!$this->data['enabled']) {

            $this->session->set_flashdata('error', '<strong>Sorry,</strong> uploads are not available right now.');
            redirect($return);
        }

        // --------------------------------------------------------------------------

        //  Upload the file
        if ($this->oCdn->objectCreate('userfile', $this->data['bucket']->id)) {

            $this->session->set_flashdata('success', '<strong>Success!</strong> File uploaded successfully!');

        } else {

            $this->session->set_flashdata('error', '<strong>Sorry,</strong> ' . $this->oCdn->lastError());
        }

        redirect($return);
    }

    // --------------------------------------------------------------------------

    /**
     * Delete an object
     * @return void
     */
    public function delete()
    {
        //  Returning to...?
        $return  = site_url('cdn/manager/browse', isPageSecure());
        $return .= $this->input->server('QUERY_STRING') ? '?' . $this->input->server('QUERY_STRING') : '';

        // --------------------------------------------------------------------------

        //  User is authorised to delete?
        if (!$this->data['enabled']) {

            $status  = 'error';
            $message = '<strong>Sorry,</strong> file deletions are not available right now.';
            $this->session->set_flashdata($status, $message);
            redirect($return);
        }

        // --------------------------------------------------------------------------

        //  Fetch the object
        if (!$this->uri->segment(4)) {

            $this->session->set_flashdata('error', '<strong>Sorry,</strong> invalid object.');
            redirect($return);
        }

        $object = $this->oCdn->getObject($this->uri->segment(4));

        if (!$object) {

            $this->session->set_flashdata('error', '<strong>Sorry,</strong> invalid object.');
            redirect($return);
        }

        // --------------------------------------------------------------------------

        //  Attempt Delete
        $delete = $this->oCdn->objectDelete($object->id);

        if ($delete) {

            $url = 'cdn/manager/restore/' . $this->uri->segment(4) . '?' . $this->input->server('QUERY_STRING');
            $url = site_url($url, isPageSecure());

            $status  = 'success';
            $message = '<strong>Success!</strong> File deleted successfully! <a href="' . $url . '">Undo?</a>';

            $this->session->set_flashdata($status, $message);
            $this->session->set_flashdata('deleted', true);

        } else {

            $this->session->set_flashdata('error', '<strong>Sorry,</strong> ' . $this->oCdn->lastError());
        }

        // --------------------------------------------------------------------------

        redirect($return);
    }

    // --------------------------------------------------------------------------

    /**
     * Restore a deleted object
     * @return void
     */
    public function restore()
    {
        //  Returning to...?
        $return  = site_url('cdn/manager/browse', isPageSecure());
        $return .= $this->input->server('QUERY_STRING') ? '?' . $this->input->server('QUERY_STRING') : '';

        // --------------------------------------------------------------------------

        //  User is authorised to restore??
        if (!$this->data['enabled']) {

            $status  = 'error';
            $message = '<strong>Sorry,</strong> file restorations are not available right now.';
            $this->session->set_flashdata($status, $message);
            redirect($return);
        }

        // --------------------------------------------------------------------------

        //  Fetch the object
        if (!$this->uri->segment(4)) {

            $this->session->set_flashdata('error', '<strong>Sorry,</strong> invalid object.');
            redirect($return);
        }

        $object = $this->oCdn->getObjectFromTrash($this->uri->segment(4));

        if (!$object) {

            $this->session->set_flashdata('error', '<strong>Sorry,</strong> invalid object.');
            redirect($return);
        }

        // --------------------------------------------------------------------------

        //  Attempt Restore
        $restore = $this->oCdn->objectRestore($object->id);

        if ($restore) {

            $this->session->set_flashdata('success', '<strong>Success!</strong> File restored successfully!');

        } else {

            $this->session->set_flashdata('error', '<strong>Sorry,</strong> ' . $this->oCdn->lastError());
        }

        // --------------------------------------------------------------------------

        redirect($return);
    }
}
