<?php

/**
 * This class is the "Image" CMS widget definition
 *
 * @package     Nails
 * @subpackage  module-cms
 * @category    Widget
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Cdn\Cms\Widget;

use Nails\Factory;
use Nails\Cms\Widget\WidgetBase;

class Image extends WidgetBase
{
    /**
     * Construct and define the widget
     */
    public function __construct()
    {
        parent::__construct();

        $this->label       = 'Image';
        $this->icon        = 'fa-picture-o';
        $this->description = 'A single image.';
        $this->keywords    = 'image,images,photo,photos';
    }

    // --------------------------------------------------------------------------

    /**
     * @param array $aWidgetData The data to use to compile the widget
     */
    protected function populateWidgetData(array &$aWidgetData)
    {
        $aWidgetData['iImageId']  = (int) getFromArray(['iImageId', 'image_id'], $aWidgetData) ?: null;
        $aWidgetData['sScaling']  = getFromArray(['sScaling', 'scaling'], $aWidgetData);
        $aWidgetData['sSize']     = getFromArray(['sSize', 'size'], $aWidgetData);
        $aWidgetData['sLinking']  = getFromArray(['sLinking', 'linking'], $aWidgetData);
        $aWidgetData['sUrl']      = getFromArray(['sUrl', 'url'], $aWidgetData);
        $aWidgetData['sTarget']   = getFromArray(['sTarget', 'target'], $aWidgetData);
        $aWidgetData['sImgAttr']  = getFromArray(['sImgAttr', 'img_attr'], $aWidgetData);
        $aWidgetData['sLinkAttr'] = getFromArray(['sLinkAttr', 'link_attr'], $aWidgetData);

        $oCdn        = Factory::service('Cdn', 'nails/module-cdn');
        $aOptions    = ['Landscape' => [], 'Portrait' => [], 'Square' => []];
        $aDimensions = $oCdn->getPermittedDimensions();

        foreach ($aDimensions as $oDimension) {

            $sKey   = $oDimension->width . 'x' . $oDimension->height;
            $sLabel = $oDimension->width . 'px by ' . $oDimension->height . 'px';

            if ($oDimension->width < $oDimension->height) {
                $aOptions['Portrait'][$sKey] = $sLabel;
            } elseif ($oDimension->width > $oDimension->height) {
                $aOptions['Landscape'][$sKey] = $sLabel;
            } else {
                $aOptions['Square'][$sKey] = $sLabel;
            }
        }

        $aWidgetData['aDimensions'] = $aOptions;

        /**
         * Backwards compatability
         *
         * The previous version of this widget allowed for arbritray image dimensions; if they are set,
         * and valid then allow them to be displayed.
         */
        if (empty($aWidgetData['sSize'])) {

            $iWidth  = (int) getFromArray('width', $aWidgetData) ?: null;
            $iHeight = (int) getFromArray('height', $aWidgetData) ?: null;

            if (!empty($iWidth) && !empty($iHeight)) {

                foreach ($aDimensions as $oDimension) {
                    if ($oDimension->width === $iWidth && $oDimension->height === $iHeight) {
                        $aWidgetData['sSize'] = $iWidth . 'x' . $iHeight;
                    }
                }
            }
        }

        parent::populateWidgetData($aWidgetData);
    }
}
