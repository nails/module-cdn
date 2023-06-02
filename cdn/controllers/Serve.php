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

use Nails\Cdn\Constants;
use Nails\Cdn\Controller\Base;
use Nails\Config;
use Nails\Factory;

/**
 * Class Serve
 */
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
        /** @var \Nails\Common\Service\Input $oInput */
        $oInput = Factory::service('Input');
        $sToken = $oInput->get('token');

        if ($sToken) {

            //  Encrypted token/expiring URL
            /** @var \Nails\Common\Service\Encrypt $oEncrypt */
            $oEncrypt = Factory::service('Encrypt');
            $sToken   = $oEncrypt->decode($sToken, Config::get('PRIVATE_KEY'));
            $aToken   = explode('|', $sToken);

            if (count($aToken) == 5) {

                $this->badToken = false;

                //  Seems to be ok, but verify the different parts
                [$bucket, $object, $expires, $time, $hash] = $aToken;

                if (md5($time . $bucket . $object . $expires . Config::get('PRIVATE_KEY')) == $hash) {

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

            /** @var \Nails\Common\Service\Uri $oUri */
            $oUri           = Factory::service('Uri');
            $this->badToken = false;
            $this->bucket   = $oUri->segment(3);
            $this->object   = urldecode((string) $oUri->segment(4));
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Serve the file
     *
     * @return void
     */
    public function index()
    {
        /** @var \Nails\Cdn\Service\Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        /** @var \Nails\Common\Service\Input $oInput */
        $oInput = Factory::service('Input');
        /** @var \Nails\Common\Service\Logger $oLogger */
        $oLogger = Factory::service('Logger');

        //  Check if there was a bad token
        if ($this->badToken) {
            $oLogger->line('CDN: Serve: Bad Token');
            $this->serveBadSrc([
                'error' => 'Bad Token',
            ]);
        }

        // --------------------------------------------------------------------------

        //  Look up the object in the DB
        $oObject = $oCdn->getObject($this->object, $this->bucket);

        if (!$oObject) {

            /**
             * If trashed=1 GET param is set and user is a logged in admin with
             * can_browse_trash permission then have a look in the trash
             */

            if ($oInput->get('trashed') && userHasPermission(\Nails\Cdn\Admin\Permission\Object\Trash\Browse::class)) {

                $oObject = $oCdn->getObjectFromTrash($this->object, $this->bucket);

                if (!$oObject) {
                    //  Cool, guess it really doesn't exist
                    $oLogger->line('CDN: Serve: Object not defined');
                    $this->serveBadSrc([
                        'error' => 'Object not defined',
                    ]);
                }

            } else {
                $oLogger->line('CDN: Serve: Object not defined');
                $this->serveBadSrc([
                    'error' => 'Object not defined',
                ]);
            }
        }

        // --------------------------------------------------------------------------

        /**
         * Check the request headers; avoid hitting the disk at all if possible. If
         * the Etag matches then send a Not-Modified header and terminate execution.
         */

        if ($this->serveNotModified($this->bucket . $this->object)) {
            if ($oObject) {
                if ($oInput->get('dl')) {
                    $oCdn->objectIncrementCount('DOWNLOAD', $oObject->id);
                } else {
                    $oCdn->objectIncrementCount('SERVE', $oObject->id);
                }
            }

            return;
        }

        // --------------------------------------------------------------------------

        //  Fetch source
        $sLocalPath = $oCdn->objectLocalPath($oObject->id);

        if (!$sLocalPath) {

            $oLogger->line('CDN: Serve: File does not exist');
            $oLogger->line('CDN: Serve: ' . $oCdn->lastError());

            if (isSuperUser()) {
                $this->serveBadSrc([
                    'error' => 'File not found: ' . $sLocalPath,
                ]);
            } else {
                $this->serveBadSrc([
                    'error' => 'File not found',
                ]);
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
            if ($oObject) {
                header('Content-Disposition: attachment; filename="' . $oObject->file->name->human . '"', true);
                header('Content-Length: ' . $oObject->file->size->bytes, true);
            } else {
                header('Content-Disposition: attachment; filename="' . $this->object . '"', true);
            }

        } else {

            //  Determine headers to send
            header('Content-Type: ' . $oObject->file->mime, true);

            //  If the object is known about, add some extra headers
            if ($oObject) {
                header('Content-Disposition: inline; filename="' . $oObject->file->name->human . '"', true);
                header('Content-Length: ' . $oObject->file->size->bytes, true);
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
            readFileChunked($sLocalPath);
        }

        // --------------------------------------------------------------------------

        //  Bump the counter
        if ($oObject) {
            if ($oInput->get('dl')) {
                $oCdn->objectIncrementCount('DOWNLOAD', $oObject->id);
            } else {
                $oCdn->objectIncrementCount('SERVE', $oObject->id);
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
     * @param array $params
     *
     * @internal param string $error The error which occurred
     *
     */
    protected function serveBadSrc(array $params)
    {
        $sError = $params['error'];

        /** @var \Nails\Common\Service\Input $oInput */
        $oInput = Factory::service('Input');
        header('Cache-Control: no-cache, must-revalidate', true);
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT', true);
        header('Content-Type: application/json', true);
        header($oInput->server('SERVER_PROTOCOL') . ' 400 Bad Request', true, 400);

        // --------------------------------------------------------------------------

        $aOut = [
            'status'  => 400,
            'message' => 'Invalid Request',
        ];

        if (!empty($sError)) {
            $aOut['error'] = $sError;
        }

        echo json_encode($aOut);

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
     *
     * @return void
     */
    public function _remap()
    {
        $this->index();
    }
}
