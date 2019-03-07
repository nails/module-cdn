<?php

/**
 * This class is the "Image" CMS widget view
 *
 * @package     Nails
 * @subpackage  module-cms
 * @category    Widget
 * @author      Nails Dev Team
 * @link
 */

if ($iImageId && $sSize) {

    //  Determine image URL
    list($sWidth, $sHeight) = explode('x', $sSize);

    if ($sScaling == 'CROP' && $sWidth && $sHeight) {
        $sImgUrl = cdnCrop($iImageId, $sWidth, $sHeight);
    } elseif ($sScaling == 'SCALE' && $sWidth && $sHeight) {
        $sImgUrl = cdnScale($iImageId, $sWidth, $sHeight);
    } else {
        $sImgUrl = cdnServe($iImageId);
    }

    // --------------------------------------------------------------------------

    //  Determine linking
    if ($sLinking == 'CUSTOM' && $sUrl) {
        $sLinkUrl    = $sUrl;
        $sLinkTarget = $sTarget ? 'target="' . $sTarget . '"' : '';
    } elseif ($sLinking == 'FULLSIZE') {
        $sLinkUrl    = cdnServe($iImageId);
        $sLinkTarget = $sTarget ? 'target="' . $sTarget . '"' : '';
    } else {
        $sLinkUrl    = '';
        $sLinkTarget = '';
    }

    // --------------------------------------------------------------------------

    // Render
    $sOut = '';
    $sOut .= $sLinkUrl ? '<a href="' . $sLinkUrl . '" ' . $sLinkAttr . $sLinkTarget . '>' : '';
    $sOut .= '<img src="' . $sImgUrl . '" ' . $sImgAttr . '/>';
    $sOut .= $sLinkUrl ? '</a>' : '';

    echo $sOut;
}
