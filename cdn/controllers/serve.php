<?php

//  Include _cdn.php; executes common functionality
require_once '_cdn.php';

/**
 * This class handles the "serve" CDN endpoint
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

class NAILS_Serve extends NAILS_CDN_Controller
{
    private $bucket;
    private $object;
    private $badToken;

    // --------------------------------------------------------------------------

    /**
     * Construct the controller
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  Work out some variables
        $token = $this->input->get('token');

        if ($token) {

            //  Encrypted token/expiring URL
            $token = $this->encrypt->decode($token, APP_PRIVATE_KEY);
            $token = explode('|', $token);

            if (count($token) == 5) {

                $this->badToken   = false;

                //  Seems to be ok, but verify the different parts
                list($bucket, $object, $expires, $time, $hash) = $token;

                if (md5($time . $bucket . $object . $expires . APP_PRIVATE_KEY) == $hash) {

                    //  Hash validates, URL expired?
                    if (time() <= ($time + $expires)) {

                        $this->bucket   = $bucket;
                        $this->object   = $object;
                        $this->badToken = false;

                    } else {

                        $this->badToken = true;
                    }

                } else {

                    $this->badToken = true;
                }

            } else {

                $this->badToken = true;
            }

        } else {

            $this->badToken = false;
            $this->bucket   = $this->uri->segment(3);
            $this->object   = urldecode($this->uri->segment(4));
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Serve the file
     * @return void
     */
    public function index()
    {
        //  Check if there was a bad token
        if ($this->badToken) {

            log_message('error', 'CDN: Serve: Bad Token');
            return $this->serveBadSrc(lang('cdn_error_serve_badToken'));
        }

        // --------------------------------------------------------------------------

        //  Look up the object in the DB
        $object = $this->cdn->get_object($this->object, $this->bucket);

        if (!$object) {

            /**
             * If trashed=1 GET param is set and user is a logged in admin with
             * can_browse_trash permission then have a look in the trash
             */

            if ($this->input->get('trashed') && userHasPermission('admin:cdn:trash:browse')) {

                $object = $this->cdn->get_object_from_trash($this->object, $this->bucket);

                if (!$object) {

                    //  Cool, guess it really doesn't exist
                    log_message('error', 'CDN: Serve: Object not defined');
                    return $this->serveBadSrc(lang('cdn_error_serve_object_not_defined'));
                }

            } else {

                log_message('error', 'CDN: Serve: Object not defined');
                return $this->serveBadSrc(lang('cdn_error_serve_object_not_defined'));
            }
        }

        // --------------------------------------------------------------------------

        /**
         * Check the request headers; avoid hitting the disk at all if possible. If
         * the Etag matches then send a Not-Modified header and terminate execution.
         */

        if ($this->serveNotModified($this->bucket . $this->object)) {

            if ($object) {

                if ($this->input->get('dl')) {

                    $this->cdn->object_increment_count('DOWNLOAD', $object->id);

                } else {

                    $this->cdn->object_increment_count('SERVE', $object->id);
                }
            }

            return;
        }

        // --------------------------------------------------------------------------

        //  Fetch source
        $usefile = $this->cdn->object_local_path($this->bucket, $this->object);

        if (!$usefile) {

            log_message('error', 'CDN: Serve: File does not exist');
            log_message('error', 'CDN: Serve: ' . $this->cdn->last_error());

            if ($this->user_model->isSuperuser()) {

                return $this->serveBadSrc(lang('cdn_error_serve_file_not_found') . ': ' . $usefile);

            } else {

                return $this->serveBadSrc(lang('cdn_error_serve_file_not_found'));
            }
        }

        // --------------------------------------------------------------------------

        //  Determine headers to send. Are we forcing the download?
        if ($this->input->get('dl')) {

            header('Content-Description: File Transfer', true);
            header('Content-Type: application/octet-stream', true);
            header('Content-Transfer-Encoding: binary', true);
            header('Expires: 0', true);
            header('Cache-Control: must-revalidate', true);
            header('Pragma: public', true);

            //  If the object is known about, add some extra headers
            if ($object) {

                header('Content-Disposition: attachment; filename="' . $object->filename_display . '"', true);
                header('Content-Length: ' . $object->filesize, true);

            } else {

                header('Content-Disposition: attachment; filename="' . $this->object . '"', true);
            }

        } else {

            //  Determine headers to send
            $finfo = new finfo(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
            header('Content-type: ' . $finfo->file($usefile), true);

            $stats = stat($usefile);
            $this->setCacheHeaders($stats[9], $this->bucket . $this->object, false);

            // --------------------------------------------------------------------------

            //  If the object is known about, add some extra headers
            if ($object) {

                header('Content-Length: ' . $object->filesize, true);
            }
        }

        // --------------------------------------------------------------------------

        //  Send the contents of the file to the browser
        echo readFileChunked($usefile);

        // --------------------------------------------------------------------------

        //  Bump the counter
        if ($object) {

            if ($this->input->get('dl')) {

                $this->cdn->object_increment_count('DOWNLOAD', $object->id);

            } else {

                $this->cdn->object_increment_count('SERVE', $object->id);
            }
        }

        // --------------------------------------------------------------------------

        /**
         * Kill script, th, th, that's all folks. Stop the output class from hijacking
         * our headers and setting an incorrect Content-Type
         */

        exit(0);
    }

    // --------------------------------------------------------------------------

    /**
     * Serves a response for bad requests
     * @param  string $error The error which occurred
     * @return void
     */
    protected function serveBadSrc($error = '')
    {
        header('Cache-Control: no-cache, must-revalidate', true);
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT', true);
        header('Content-type: application/json', true);
        header($this->input->server('SERVER_PROTOCOL') . ' 400 Bad Request', true, 400);

        // --------------------------------------------------------------------------

        $out = array(
            'status'  => 400,
            'message' => lang('cdn_error_serve_invalid_request')
        );

        if (!empty($error)) {

            $out['error'] = $error;
        }

        echo json_encode($out);

        // --------------------------------------------------------------------------

        /**
         * Kill script, th, th, that's all folks. Stop the output class from hijacking
         * our headers and setting an incorrect Content-Type
         */

        exit(0);
    }

    // --------------------------------------------------------------------------

    /**
     * Map all requests to index()
     * @return void
     */
    public function _remap()
    {
        $this->index();
    }
}


// --------------------------------------------------------------------------


/**
 * OVERLOADING NAILS' CDN MODULES
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
 */

if (!defined('NAILS_ALLOW_EXTENSION_SERVE')) :

    class Serve extends NAILS_Serve
    {
    }

endif;
