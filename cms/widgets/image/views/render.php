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
    [$sWidth, $sHeight] = explode('x', $sSize);

    try {

        if ($sScaling == 'CROP' && $sWidth && $sHeight) {
            $sImgUrl = (string) cdnCrop($iImageId, $sWidth, $sHeight);

        } elseif ($sScaling == 'SCALE' && $sWidth && $sHeight) {
            $sImgUrl = (string) cdnScale($iImageId, $sWidth, $sHeight);

        } else {
            $sImgUrl = (string) cdnServe($iImageId);
        }

        // --------------------------------------------------------------------------

        //  Determine linking
        if ($sLinking == 'CUSTOM' && $sUrl) {
            $sLinkUrl    = $sUrl;
            $sLinkTarget = $sTarget ? 'target="' . $sTarget . '"' : '';

        } elseif ($sLinking == 'FULLSIZE') {
            $sLinkUrl    = (string) cdnServe($iImageId);
            $sLinkTarget = $sTarget ? 'target="' . $sTarget . '"' : '';

        } else {
            $sLinkUrl    = '';
            $sLinkTarget = '';
        }

        // --------------------------------------------------------------------------

        // Render
        $sOut = '<div class="cms-widget cms-widget-image">';
        $sOut .= $sLinkUrl ? '<a href="' . $sLinkUrl . '" ' . $sLinkAttr . $sLinkTarget . '>' : '';
        $sOut .= '<img src="' . $sImgUrl . '" ' . $sImgAttr . '/>';
        $sOut .= $sLinkUrl ? '</a>' : '';
        $sOut .= '</div>';

        echo $sOut;

    } catch (\Nails\Cdn\Exception\CdnException $e) {
        //  Don't output anything
    }
}
