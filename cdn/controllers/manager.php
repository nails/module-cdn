<?php

//  Include _cdn.php; executes common functionality
require_once '_cdn.php';

/**
 * This class provides the CDN manager, a one stop shop for managing objects in buckets
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

class NAILS_Manager extends NAILS_CDN_Controller
{
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
        $this->load->library('cdn/cdn');

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

                            $this->data['bucket'] = $this->cdn->get_bucket($bucket[0]);

                            if ($this->data['bucket']) {

                                $testOk = true;

                            } else {

                                //  Bucket doesn't exist - attempt to create it
                                if ($this->cdn->bucket_create($bucket[0])) {

                                    $testOk = true;
                                    $this->data['bucket'] = $this->cdn->get_bucket($bucket[0]);

                                } else {

                                    $testOk = false;
                                    $error  = 'Bucket <strong>"' . $bucket[0] . '"</strong> does not exist';
                                    $error .= '<small>Additionally, the following error occured while attempting ';
                                    $error .= 'to create the bucket:<br />' . $this->cdn->last_error() . '</small>';
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
                $this->data['bucket'] = $this->cdn->get_bucket($slug);

                if (!$this->data['bucket']) {

                    $bucket_id = $this->cdn->bucket_create($slug, $label);

                    if (!$bucket_id) {

                         $this->data['enabled']    = false;
                         $this->data['badBucket'] = 'Unable to create upload bucket: ' . $this->cdn->last_error();

                    } else {

                        $this->data['bucket'] = $this->cdn->get_bucket($bucket_id);
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
            $this->asset->load('mustache.js/mustache.js', 'NAILS-BOWER');
            $this->asset->load('jquery-cookie/jquery.cookie.js', 'NAILS-BOWER');
            // $this->asset->load('dropzone/downloads/dropzone.min.js', 'NAILS-BOWER');
            $this->asset->load('fontawesome/css/font-awesome.min.css', 'NAILS-BOWER');

            //  Load other assets
            $this->asset->load('nails.default.min.js', true);
            $this->asset->load('nails.api.min.js', true);
            $this->asset->load('nails.cdn.manager.min.js', true);

            // --------------------------------------------------------------------------

            //  List the bucket objects
            $this->data['objects'] = $this->cdn->bucket_list($this->data['bucket']->id);

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
        $this->load->library('cdn/cdn');
        if ($this->cdn->object_create('userfile', $this->data['bucket']->id)) {

            $this->session->set_flashdata('success', '<strong>Success!</strong> File uploaded successfully!');

        } else {

            $this->session->set_flashdata('error', '<strong>Sorry,</strong> ' . $this->cdn->last_error());
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

        $this->load->library('cdn/cdn');

        //  Fetch the object
        if (!$this->uri->segment(4)) {

            $this->session->set_flashdata('error', '<strong>Sorry,</strong> invalid object.');
            redirect($return);
        }

        $object = $this->cdn->get_object($this->uri->segment(4));

        if (!$object) {

            $this->session->set_flashdata('error', '<strong>Sorry,</strong> invalid object.');
            redirect($return);
        }

        // --------------------------------------------------------------------------

        //  Attempt Delete
        $delete = $this->cdn->object_delete($object->id);

        if ($delete) {

            $url = 'cdn/manager/restore/' . $this->uri->segment(4) . '?' . $this->input->server('QUERY_STRING');
            $url = site_url($url, isPageSecure());

            $status  = 'success';
            $message = '<strong>Success!</strong> File deleted successfully! <a href="' . $url . '">Undo?</a>';
            $this->session->set_flashdata($status, $message);

            $this->session->set_flashdata('deleted', true);

        } else {

            $this->session->set_flashdata('error', '<strong>Sorry,</strong> ' . $this->cdn->last_error());
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

        $this->load->library('cdn/cdn');

        //  Fetch the object
        if (!$this->uri->segment(4)) {

            $this->session->set_flashdata('error', '<strong>Sorry,</strong> invalid object.');
            redirect($return);
        }

        $object = $this->cdn->get_object_from_trash($this->uri->segment(4));

        if (!$object) {

            $this->session->set_flashdata('error', '<strong>Sorry,</strong> invalid object.');
            redirect($return);
        }

        // --------------------------------------------------------------------------

        //  Attempt Restore
        $restore = $this->cdn->object_restore($object->id);

        if ($restore) {

            $this->session->set_flashdata('success', '<strong>Success!</strong> File restored successfully!');

        } else {

            $this->session->set_flashdata('error', '<strong>Sorry,</strong> ' . $this->cdn->last_error());
        }

        // --------------------------------------------------------------------------

        redirect($return);
    }
}

// --------------------------------------------------------------------------

/**
 * OVERLOADING NAILS' CDN MODULE
 *
 * The following block of code makes it simple to extend one of the core CDN
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
 * If/when we want to extend the main class we simply define NAILS_ALLOW_EXTENSION
 * before including this PHP file and extend as normal (i.e in the same way as below);
 * the helper won't be declared so we can declare our own one, app specific.
 *
 **/

if (!defined('NAILS_ALLOW_EXTENSION')) {

    class Manager extends NAILS_Manager
    {
    }
}
