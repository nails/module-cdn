<?php

/**
 * This class handles the "serve" CDN endpoint
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

use Nails\Cdn\Controller\Base;
use Nails\Factory;

class Serve extends Base
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
        $oInput = Factory::service('Input');
        $token  = $oInput->get('token');

        if ($token) {

            //  Encrypted token/expiring URL
            $oEncrypt = Factory::service('Encrypt');
            $token    = $oEncrypt->decode($token, APP_PRIVATE_KEY);
            $token    = explode('|', $token);

            if (count($token) == 5) {

                $this->badToken = false;

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

            $oUri           = Factory::service('Uri');
            $this->badToken = false;
            $this->bucket   = $oUri->segment(3);
            $this->object   = urldecode($oUri->segment(4));
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Serve the file
     * @return void
     */
    public function index()
    {
        $oCdn   = Factory::service('Cdn', 'nailsapp/module-cdn');
        $oInput = Factory::service('Input');
        //  Check if there was a bad token
        if ($this->badToken) {
            log_message('error', 'CDN: Serve: Bad Token');
            $this->serveBadSrc('Bad Token');
        }

        // --------------------------------------------------------------------------

        //  Look up the object in the DB
        $object = $oCdn->getObject($this->object, $this->bucket);

        if (!$object) {

            /**
             * If trashed=1 GET param is set and user is a logged in admin with
             * can_browse_trash permission then have a look in the trash
             */

            if ($oInput->get('trashed') && userHasPermission('admin:cdn:trash:browse')) {

                $object = $oCdn->getObjectFromTrash($this->object, $this->bucket);

                if (!$object) {
                    //  Cool, guess it really doesn't exist
                    log_message('error', 'CDN: Serve: Object not defined');
                    $this->serveBadSrc('Object not defined');
                }

            } else {

                log_message('error', 'CDN: Serve: Object not defined');
                $this->serveBadSrc('Object not defined');
            }
        }

        // --------------------------------------------------------------------------

        /**
         * Check the request headers; avoid hitting the disk at all if possible. If
         * the Etag matches then send a Not-Modified header and terminate execution.
         */

        if ($this->serveNotModified($this->bucket . $this->object)) {
            if ($object) {
                if ($oInput->get('dl')) {
                    $oCdn->objectIncrementCount('DOWNLOAD', $object->id);
                } else {
                    $oCdn->objectIncrementCount('SERVE', $object->id);
                }
            }

            return;
        }

        // --------------------------------------------------------------------------

        //  Fetch source
        $usefile = $oCdn->objectLocalPath($object->id);

        if (!$usefile) {

            log_message('error', 'CDN: Serve: File does not exist');
            log_message('error', 'CDN: Serve: ' . $oCdn->lastError());

            if (isSuperuser()) {
                $this->serveBadSrc('File not found: ' . $usefile);
            } else {
                $this->serveBadSrc('File not found');
            }
        }

        // --------------------------------------------------------------------------

        //  Determine headers to send. Are we forcing the download?
        if ($oInput->get('dl')) {

            header('Content-Description: File Transfer', true);
            header('Content-Type: application/octet-stream', true);
            header('Content-Transfer-Encoding: binary', true);
            header('Expires: 0', true);
            header('Cache-Control: must-revalidate', true);
            header('Pragma: public', true);

            //  If the object is known about, add some extra headers
            if ($object) {

                header('Content-Disposition: attachment; filename="' . $object->file->name->human . '"', true);
                header('Content-Length: ' . $object->file->size->bytes, true);

            } else {

                header('Content-Disposition: attachment; filename="' . $this->object . '"', true);
            }

        } else {

            //  Determine headers to send
            header('Content-Type: ' . $object->file->mime, true);

            $stats = stat($usefile);
            $this->setCacheHeaders($stats[9], $this->bucket . $this->object, false);

            // --------------------------------------------------------------------------

            //  If the object is known about, add some extra headers
            if ($object) {
                header('Content-Length: ' . $object->file->size->bytes, true);
            }
        }

        // --------------------------------------------------------------------------

        //  Send the contents of the file to the browser
        //  If a particular range of bytes is being requested then send those.
        //  header('Accept-Ranges: bytes');
        if (isset($_SERVER['HTTP_RANGE'])) {

            //  @todo - support ranges
            //  See http://www.media-division.com/the-right-way-to-handle-file-downloads-in-php/

        } else {

            Factory::helper('file');
            readFileChunked($usefile);
        }

        // --------------------------------------------------------------------------

        //  Bump the counter
        if ($object) {
            if ($oInput->get('dl')) {
                $oCdn->objectIncrementCount('DOWNLOAD', $object->id);
            } else {
                $oCdn->objectIncrementCount('SERVE', $object->id);
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
     *
     * @param  string $error The error which occurred
     *
     * @return void
     */
    protected function serveBadSrc($error = '')
    {
        $oInput = Factory::service('Input');
        header('Cache-Control: no-cache, must-revalidate', true);
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT', true);
        header('Content-type: application/json', true);
        header($oInput->server('SERVER_PROTOCOL') . ' 400 Bad Request', true, 400);

        // --------------------------------------------------------------------------

        $out = [
            'status'  => 400,
            'message' => 'Invalid Request',
        ];

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
