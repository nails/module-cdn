<?php

/**
 * The "Image" widget for WidgetEditor (v2). Ports the v1 widget: the stored slug `image` resolves through ALIASES.
 *
 * The v1 keys are read as fallbacks (image_id, scaling, size, linking, url, target, img_attr, link_attr), so stored
 * instances render as they did. Their values are not converted; an edit writes only the key that changed.
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Widget
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Cdn\Cms\Widgets;

use Nails\Cdn\Cms\Fields\ObjectPicker;
use Nails\Cdn\Constants;
use Nails\Cdn\Exception\CdnException;
use Nails\Cms\Cms\Fields\Select;
use Nails\Cms\Cms\Fields\Text;
use Nails\Cms\WidgetEditor\Widget\Base;
use Nails\Factory;

class Image extends Base
{
    const ALIASES = ['image'];

    protected string $label       = 'Image';
    protected string $summary     = 'A single image.';
    protected string $description = 'A single image.';
    protected string $icon        = 'fa-image';
    protected array $keywords     = ['image', 'images', 'photo', 'photos'];
    protected array $defaults     = [
        'iImageId'  => null,
        'sScaling'  => 'NONE',
        'sSize'     => '',
        'sLinking'  => 'NONE',
        'sUrl'      => '',
        'sTarget'   => '',
        'sImgAlt'   => '',
        'sImgTitle' => '',
        'sImgAttr'  => '',
        'sLinkAttr' => '',
    ];

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function getFields(): ?array
    {
        /** @var \Nails\Cdn\Service\Cdn $oCdn */
        $oCdn    = Factory::service('Cdn', Constants::MODULE_SLUG);
        $aSizes  = ['' => 'Choose a size'];
        foreach ($oCdn->getPermittedDimensions() as $oDimension) {
            $aSizes[$oDimension->width . 'x' . $oDimension->height] = $oDimension->width . 'px by ' . $oDimension->height . 'px';
        }

        return [
            ObjectPicker::make('iImageId')->label('Image')->required(),
            Select::make('sScaling')
                ->label('Scaling')
                ->tip('Crop guarantees the defined size. Scale guarantees the image does not exceed it.')
                ->options(['NONE' => 'None, show fullsize', 'CROP' => 'Crop', 'SCALE' => 'Scale']),
            Select::make('sSize')
                ->label('Size')
                ->tip('These dimensions are configured by the application.')
                ->options($aSizes),
            Select::make('sLinking')
                ->label('Linking')
                ->options(['NONE' => 'Do not link', 'FULLSIZE' => 'Link to fullsize', 'CUSTOM' => 'Custom URL']),
            Text::make('sUrl')->label('URL')->visibleWhen(['sLinking' => 'CUSTOM']),
            Select::make('sTarget')
                ->label('Target')
                ->options(['' => 'None', '_blank' => 'New window/tab', '_parent' => 'Parent window/tab']),
            Text::make('sImgAlt')->label('Alt'),
            Text::make('sImgTitle')->label('Title'),
            Text::make('sImgAttr')->label('Attributes')->tip('Extra attributes for the image tag.'),
            Text::make('sLinkAttr')->label('Link Attributes')->tip('Extra attributes for the link tag.'),
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function render(array $data): string
    {
        $iImageId = (int) $this->value($data, ['iImageId', 'image_id']);
        $sSize    = (string) $this->value($data, ['sSize', 'size']);
        if (!$iImageId || $sSize === '') {
            return '';
        }

        $sScaling  = (string) $this->value($data, ['sScaling', 'scaling']);
        $sLinking  = (string) $this->value($data, ['sLinking', 'linking']);
        $sUrl      = (string) $this->value($data, ['sUrl', 'url']);
        $sTarget   = (string) $this->value($data, ['sTarget', 'target']);
        $sImgAttr  = (string) $this->value($data, ['sImgAttr', 'img_attr']);
        $sLinkAttr = (string) $this->value($data, ['sLinkAttr', 'link_attr']);
        $sImgAlt   = (string) $this->value($data, ['sImgAlt']);
        $sImgTitle = (string) $this->value($data, ['sImgTitle']);

        [$sWidth, $sHeight] = array_pad(explode('x', $sSize), 2, '');

        try {
            if ($sScaling === 'CROP' && $sWidth && $sHeight) {
                $sImgUrl = (string) cdnCrop($iImageId, $sWidth, $sHeight);
            } elseif ($sScaling === 'SCALE' && $sWidth && $sHeight) {
                $sImgUrl = (string) cdnScale($iImageId, $sWidth, $sHeight);
            } else {
                $sImgUrl = (string) cdnServe($iImageId);
            }
        } catch (CdnException $e) {
            return '';
        }

        $sLinkUrl    = '';
        $sLinkTarget = '';
        if ($sLinking === 'CUSTOM' && $sUrl !== '') {
            $sLinkUrl    = $sUrl;
            $sLinkTarget = $sTarget !== '' ? 'target="' . $sTarget . '"' : '';
        } elseif ($sLinking === 'FULLSIZE') {
            $sLinkUrl    = (string) cdnServe($iImageId);
            $sLinkTarget = $sTarget !== '' ? 'target="' . $sTarget . '"' : '';
        }

        $sAlt   = $sImgAlt !== '' ? ' alt="' . htmlspecialchars($sImgAlt) . '"' : '';
        $sTitle = $sImgTitle !== '' ? ' title="' . htmlspecialchars($sImgTitle) . '"' : '';

        $sOut = '<div class="cms-widget cms-widget-image">';
        $sOut .= $sLinkUrl !== '' ? '<a href="' . $sLinkUrl . '" ' . $sLinkAttr . $sLinkTarget . '>' : '';
        $sOut .= '<img src="' . $sImgUrl . '" ' . $sImgAttr . $sAlt . $sTitle . '/>';
        $sOut .= $sLinkUrl !== '' ? '</a>' : '';
        $sOut .= '</div>';

        return $sOut;
    }

    // --------------------------------------------------------------------------

    /**
     * The first of the keys that is set, so a v1 key works where the v2 key is missing
     */
    private function value(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                return $data[$key];
            }
        }

        return null;
    }
}
