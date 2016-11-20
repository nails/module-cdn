<?php

use Nails\Factory;

$oCdn     = Factory::service('Cdn', 'nailsapp/module-cdn');
$oInput   = Factory::service('Input');
$oSession = Factory::service('Session', 'nailsapp/module-auth');
$oAsset   = Factory::service('Asset');
$oView    = Factory::service('View');

$filterView = $oInput->get('filter-view');

if (!$filterView) {

    $filterView = $oSession->userdata('cdn-manager-view');

    if (!$filterView) {
        $filterView = 'thumb';
    }
}

$oSession->set_userdata('cdn-manager-view', $filterView);

// --------------------------------------------------------------------------

$queryString = $oInput->server('QUERY_STRING') ? '?' . $oInput->server('QUERY_STRING') : '';

?><!DOCTYPE html>
<html>
    <head>
        <title>Media Manager</title>
        <meta charset="utf-8">
        <?php

        $oAsset->output('CSS');
        $oAsset->output('CSS-INLINE');

        ?>
        <noscript>
            <style type="text/css">

                .js-only
                {
                    display:none;
                }

            </style>
        </noscript>
    </head>
    <body>
        <div class="group-cdn manager <?=$oInput->get('isModal') ? 'isModal' : ''?>">
            <div id="dropToUpload">
                <div id="dropzone1"></div>
            </div>
            <div id="mask"></div>
            <div id="alert" <?= $success || $error || $notice || $message  ? 'style="display:block;"' : ''?>>
            <?php

            if ($success) {

                echo '<p class="alert alert-success">';
                    echo $success;
                    echo '<br /><a href="#" class="btn btn-success ok">OK</a>';
                echo '</p>';
            }

            if ($error) {

                echo '<p class="alert alert-danger">';
                    echo $error;
                    echo '<br /><a href="#" class="btn btn-success ok">OK</a>';
                    echo '<a href="#" class="btn btn-danger cancel">Cancel</a>';
                echo '</p>';

            }

            if ($notice) {

                echo '<p class="alert alert-info">';
                    echo $notice;
                    echo '<br /><a href="#" class="btn btn-success ok">OK</a>';
                echo '</p>';
            }

            if ($message) {

                echo '<p class="alert alert-warning">';
                    echo $message;
                    echo '<br /><a href="#" class="btn btn-success ok">OK</a>';
                echo '</p>';
            }

            ?>
            </div>
            <div class="browser-outer">
                <div class="browser-inner">
                    <div class="enabled">
                        <ul class="layout">
                            <li class="toolbar">
                                <?php

                                //  Support no JS - might make sense to get rid of this eventually
                                // echo '<noscript>';
                                echo form_open_multipart(site_url('cdn/manager/upload' . $queryString, isPageSecure()));
                                echo form_submit('submit', 'Upload', 'class="btn btn-xs btn-success"');
                                echo form_upload('userfile');
                                echo form_close();
                                // echo '</noscript>';

                                //  Javascript goodness
                                // echo '<div class="js-only">';

                                    // echo '<button class="btn btn-success">';
                                        // echo '<strong>Choose Files to Upload</strong>';
                                    // echo '</button>';

                                // echo '</div>';

                                //  Searchy yumminess
                                if ($objects) {

                                    echo '<input type="text" class="search js-only" id="search-text" placeholder="Search files">';
                                }

                                ?>
                            </li>
                            <li class="bucket-info">
                                Browsing bucket: <strong><?=$bucket->label?></strong>
                                <span class="view-swap">
                                    <?php

                                    //  Get the query string into an array for mutation
                                    parse_str($oInput->server('QUERY_STRING'), $query);

                                    //  Filter out any existing filter-view=
                                    unset($query['filter-view']);

                                    // --------------------------------------------------------------------------

                                    //  Item is selected?
                                    $selected  = $filterView == 'thumb' ? 'selected' : '';

                                    //  Build the URI for this item
                                    $query['filter-view'] = 'thumb';
                                    $uri  = site_url(uri_string(), isPageSecure());
                                    $uri .= $query ? '?' . http_build_query($query) : '';

                                    echo anchor($uri, 'Thumbnails', 'class="thumbnail ' . $selected . '"');

                                    // --------------------------------------------------------------------------

                                    //  Item is selected?
                                    $selected  = $filterView == 'list' ? 'selected' : '';

                                    //  Build the URI for this item
                                    $query['filter-view'] = 'list';
                                    $uri  = site_url(uri_string(), isPageSecure());
                                    $uri .= $query ? '?' . http_build_query($query) : '';

                                    echo anchor($uri, 'List', 'class="list ' . $selected . '"');

                                    // --------------------------------------------------------------------------

                                    //  Item is selected?
                                    $selected  = $filterView == 'detail' ? 'selected' : '';

                                    //  Build the URI for this item
                                    $query['filter-view'] = 'detail';
                                    $uri  = site_url(uri_string(), isPageSecure());
                                    $uri .= $query ? '?' . http_build_query($query) : '';

                                    echo anchor($uri, 'Details', 'class="detail ' . $selected . '"');

                                    ?>
                                </span>
                            </li>
                            <li class="progress">
                                uploading
                                <span class="track">
                                    <span class="bar" style="width:75%;"></span>
                                </span>
                            </li>
                            <li class="files <?=$filterView?>">
                            <?php

                            if ($objects) {

                                if ($filterView == 'list') {

                                    echo '<table>';
                                        echo '<thead>';
                                            echo '<tr class="file list head">';
                                            echo '<th class="filename">File</th>';
                                            echo '<th class="mime">Type</th>';
                                            echo '<th class="filesize">Filesize</th>';
                                            echo '<th class="modified">Modified</th>';
                                            echo '<th class="actions">Actions</th>';
                                        echo '</tr>';
                                        echo '</thead>';
                                    echo '<tbody>';

                                } else {

                                    echo '<ul>';
                                }

                                // --------------------------------------------------------------------------

                                foreach ($objects as $object) {

                                    switch ($filterView) {

                                        case 'detail':

                                            $oView->load(
                                                'cdn/manager/file-detail',
                                                array(
                                                    'object'        => &$object,
                                                    '_query_string' => &$queryString
                                                )
                                            );
                                            break;

                                        case 'list':

                                            $oView->load(
                                                'cdn/manager/file-list',
                                                array(
                                                    'object'        => &$object,
                                                    '_query_string' => &$queryString
                                                )
                                            );
                                            break;


                                        case 'thumb':
                                        default:

                                            $oView->load(
                                                'cdn/manager/file-thumb',
                                                array(
                                                    'object'        => &$object,
                                                    '_query_string' => &$queryString
                                                )
                                            );
                                            break;
                                    }
                                }

                                if ($filterView == 'list') {

                                        echo '</tbody>';
                                    echo '</table>';

                                } else {

                                    echo '</ul>';
                                }

                            } else {

                                echo '<div class="no-files">';
                                    echo '<h1>No Files</h1>';
                                    echo '<p>Upload your first file using the form above.</p>';
                                echo '</div>';
                            }

                            ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <script type="text/javascript">
            var ENVIRONMENT         = '<?=nailsEnvironment('get')?>';
            window.SITE_URL         = '<?=site_url('', isPageSecure())?>';
            window.NAILS_ASSETS_URL = '<?=NAILS_ASSETS_URL?>';
            window.NAILS_LANG       = {};
        </script>
        <?php

        $oAsset->output('JS');
        $oAsset->output('JS-INLINE');

        ?>
        <script type="text/javascript">

            var manager;

            $(function(){

                //  Initialise CDN Manager
                urlScheme              = {};
                urlScheme.serve        = '<?=$oCdn->urlServeScheme()?>';
                urlScheme.thumb        = '<?=$oCdn->urlCropScheme()?>';
                urlScheme.scale        = '<?=$oCdn->urlScaleScheme()?>';
                urlScheme.placeholder  = '<?=$oCdn->urlPlaceholderScheme()?>';
                urlScheme.blank_avatar = '<?=$oCdn->urlBlankAvatarScheme()?>';

                <?php

                $isModal     = $oInput->get('isModal') ? 'true' : 'false';
                $reopenModal = $oInput->get('reopenModal') ? $oInput->get('reopenModal') : '';

                if (isset($_GET['CKEditorFuncNum'])) {

                    echo 'manager = new NAILS_CDN_Manager("ckeditor", ' . $oInput->get('CKEditorFuncNum') . ', null, urlScheme, ' . $isModal . ', "' . $reopenModal . '");';

                    if ($oInput->get('deleted')) {

                        echo 'manager.insertCkeditor(\'\', \'\');';
                    }

                } else {

                    $callback = json_encode($oInput->get('callback'));
                    $passback = $oInput->get('passback');

                    echo 'manager = new NAILS_CDN_Manager("native", ' . $callback . ', ' . $passback . ', urlScheme, ' . $isModal . ', "' . $reopenModal . '");';

                    if ($oInput->get('deleted')) {

                        echo 'manager.insertNative("", "");';
                    }
                }

                ?>
            });

            /**

            ---------------------
            | Leaving this here as a reminder of what's been done (not much, but it took me hours >_<)
            | until such a time that a custom uploader can be written.
            ---------------------


            var counter = 0;

            $('body').on(
                'dragenter',
                function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    counter++;
                    $('#dropToUpload').show();
                }
            );
            $('body').on(
                'dragover',
                function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            );
            $('body').on(
                'dragleave',
                function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    counter--;
                    if (counter === 0) {
                        $('#dropToUpload').hide();
                    }
                }
            );

            **/

        </script>
    </body>
</html>
