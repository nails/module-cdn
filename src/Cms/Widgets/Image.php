<?php

/**
 * The "Image" widget for WidgetEditor (v2). Ports the v1 widget: the stored slug `image` resolves through ALIASES.
 *
 * v1 stored the same keys. Older saves used image_id, scaling, size, linking, url, target, img_attr and link_attr,
 * and a width and height pair. upgrade() copies those onto the current keys. A width and height pair is kept as it
 * was unless it is one of the app's permitted dimensions, in which case it becomes sSize.
 *
 * The public markup matches the v1 view. Crop and scale need a size; full size does not, because the editor hides
 * the size field unless the image is cropped or scaled. The canvas asks for an image or a size when it cannot draw
 * one, and says so when the CDN rejects the object. sAlignment is left, center or right, and the wrapper carries
 * that as a class and as text-align so the image sits there on the canvas and on the page.
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

    /** @var array<string, string> Older key => current key */
    private const LEGACY_KEYS = [
        'image_id'  => 'iImageId',
        'scaling'   => 'sScaling',
        'size'      => 'sSize',
        'linking'   => 'sLinking',
        'url'       => 'sUrl',
        'target'    => 'sTarget',
        'img_attr'  => 'sImgAttr',
        'link_attr' => 'sLinkAttr',
    ];

    protected string $label       = 'Image';
    protected string $summary     = 'A single image.';
    protected string $description = 'A single image.';
    protected string $icon        = 'fa-image';
    protected array  $keywords    = ['image', 'images', 'photo', 'photos'];
    protected array  $defaults    = [
        'iImageId'   => null,
        'sScaling'   => 'NONE',
        'sSize'      => '',
        'sAlignment' => 'left',
        'sLinking'   => 'NONE',
        'sUrl'       => '',
        'sTarget'    => '',
        'sImgAlt'    => '',
        'sImgTitle'  => '',
        'sImgAttr'   => '',
        'sLinkAttr'  => '',
    ];

    // --------------------------------------------------------------------------

    /**
     * The CDN monitor finds this object under the canonical slug and the `image` alias.
     *
     * @return string[]
     */
    public function getCdnMonitorPaths(): array
    {
        return ['iImageId'];
    }

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function getFields(): ?array
    {
        return [
            ObjectPicker::make('iImageId')
                ->label('Image')
                ->required(),

            Select::make('sScaling')
                ->label('Scaling')
                ->tip('Crop guarantees the defined size. Scale guarantees the image does not exceed it.')
                ->options([
                    'NONE'  => 'None, show fullsize',
                    'CROP'  => 'Crop',
                    'SCALE' => 'Scale',
                ]),

            Select::make('sSize')
                ->label('Size')
                ->tip('These dimensions are configured by the application.')
                ->options(static::sizeOptions($this->permittedDimensions()))
                ->visibleWhen(['sScaling' => ['CROP', 'SCALE']]),

            Select::make('sAlignment')
                ->label('Alignment')
                ->options([
                    'left'   => 'Left',
                    'center' => 'Center',
                    'right'  => 'Right',
                ]),

            Select::make('sLinking')
                ->label('Linking')
                ->options([
                    'NONE'     => 'Do not link',
                    'FULLSIZE' => 'Link to fullsize',
                    'CUSTOM'   => 'Custom URL',
                ]),

            Text::make('sUrl')
                ->label('URL')
                ->visibleWhen(['sLinking' => 'CUSTOM']),

            Select::make('sTarget')
                ->label('Target')
                ->options([
                    ''        => 'None',
                    '_blank'  => 'New window/tab',
                    '_parent' => 'Parent window/tab',
                ])
                ->visibleWhen(['sLinking' => ['FULLSIZE', 'CUSTOM']]),

            Text::make('sImgAlt')
                ->label('Alt'),

            Text::make('sImgTitle')
                ->label('Title'),

            Text::make('sImgAttr')
                ->label('Attributes')
                ->tip('Extra attributes for the image tag.'),

            Text::make('sLinkAttr')
                ->label('Link Attributes')
                ->tip('Extra attributes for the link tag.')
                ->visibleWhen(['sLinking' => ['FULLSIZE', 'CUSTOM']]),
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * The size field's options, with an empty choice first, then landscape, portrait and square
     *
     * @param object[] $dimensions Each has a width and a height
     *
     * @return array<string, string>
     */
    public static function sizeOptions(array $dimensions): array
    {
        $landscape = [];
        $portrait  = [];
        $square    = [];

        foreach ($dimensions as $dimension) {
            $width  = (int) $dimension->width;
            $height = (int) $dimension->height;
            $key    = $width . 'x' . $height;
            $label  = $width . 'px by ' . $height . 'px';

            if ($width < $height) {
                $portrait[$key] = $label;
            } elseif ($width > $height) {
                $landscape[$key] = $label;
            } else {
                $square[$key] = $label;
            }
        }

        return ['' => 'Choose a size'] + $landscape + $portrait + $square;
    }

    // --------------------------------------------------------------------------

    /**
     * @return object[]
     */
    protected function permittedDimensions(): array
    {
        /** @var \Nails\Cdn\Service\Cdn $cdn */
        $cdn = Factory::service('Cdn', Constants::MODULE_SLUG);

        return array_values($cdn->getPermittedDimensions());
    }

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function upgrade(array $data): array
    {
        foreach (self::LEGACY_KEYS as $from => $to) {
            if (!array_key_exists($to, $data) && array_key_exists($from, $data)) {
                $data[$to] = $data[$from];
            }
            unset($data[$from]);
        }

        $width  = (int) ($data['width'] ?? 0);
        $height = (int) ($data['height'] ?? 0);
        if (($data['sSize'] ?? '') === '' && $width && $height) {
            $pair = $width . 'x' . $height;
            if (in_array($pair, $this->permittedSizeKeys(), true)) {
                $data['sSize'] = $pair;
                unset($data['width'], $data['height']);
            }
        }

        return $data;
    }

    // --------------------------------------------------------------------------

    /**
     * @return string[]
     */
    private function permittedSizeKeys(): array
    {
        $keys = array_keys(static::sizeOptions($this->permittedDimensions()));

        return array_values(array_filter($keys, static fn(string $key) => $key !== ''));
    }

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function render(array $data): string
    {
        $id        = (int) ($data['iImageId'] ?? 0);
        $scaling   = (string) ($data['sScaling'] ?? 'NONE');
        $size      = (string) ($data['sSize'] ?? '');
        $needsSize = $scaling === 'CROP' || $scaling === 'SCALE';
        $alignment = $this->alignment($data);

        if (!$id) {
            return $this->frame($this->hint('Choose an image in the inspector.'), $alignment);
        }
        if ($needsSize && $size === '') {
            return $this->frame($this->hint('Choose a size in the inspector.'), $alignment);
        }

        [$width, $height] = array_pad(explode('x', $size), 2, '');

        try {
            $imageUrl = $this->servedUrl($id, $scaling, $width, $height);
            $linkUrl  = $this->linkUrl($id, (string) ($data['sLinking'] ?? ''), (string) ($data['sUrl'] ?? ''));
        } catch (CdnException $e) {
            return $this->frame($this->hint('This image could not be loaded.'), $alignment);
        }

        $alt      = (string) ($data['sImgAlt'] ?? '');
        $title    = (string) ($data['sImgTitle'] ?? '');
        $imgAttr  = (string) ($data['sImgAttr'] ?? '');
        $linkAttr = (string) ($data['sLinkAttr'] ?? '');
        $target   = (string) ($data['sTarget'] ?? '');

        $img = '<img src="' . $imageUrl . '"'
            . ($imgAttr !== '' ? ' ' . $imgAttr : '')
            . ($alt !== '' ? ' alt="' . htmlspecialchars($alt) . '"' : '')
            . ($title !== '' ? ' title="' . htmlspecialchars($title) . '"' : '')
            . ' />';

        if ($linkUrl !== '') {
            $img = '<a href="' . $linkUrl . '"'
                . ($linkAttr !== '' ? ' ' . $linkAttr : '')
                . ($target !== '' ? ' target="' . $target . '"' : '')
                . '>' . $img . '</a>';
        }

        return $this->frame($img, $alignment);
    }

    // --------------------------------------------------------------------------

    private function hint(string $message): string
    {
        return $this->isPreview() ? '<p class="cms-we-hint">' . $message . '</p>' : '';
    }

    // --------------------------------------------------------------------------

    /**
     * Left unless the stored value is center or right
     */
    private function alignment(array $data): string
    {
        $alignment = (string) ($data['sAlignment'] ?? 'left');

        return in_array($alignment, ['left', 'center', 'right'], true) ? $alignment : 'left';
    }

    // --------------------------------------------------------------------------

    private function frame(string $inner, string $alignment): string
    {
        if ($inner === '') {
            return '';
        }

        return '<div class="cms-widget cms-widget-image cms-widget-image--' . $alignment . '"'
            . ' style="text-align:' . $alignment . '">' . $inner . '</div>';
    }

    // --------------------------------------------------------------------------

    /**
     * @throws CdnException
     */
    private function servedUrl(int $id, string $scaling, string $width, string $height): string
    {
        if (($scaling === 'CROP' || $scaling === 'SCALE') && $width !== '' && $height !== '') {
            $url = $scaling === 'CROP'
                ? cdnCrop($id, (int) $width, (int) $height)
                : cdnScale($id, (int) $width, (int) $height);

            return (string) $url;
        }

        return (string) cdnServe($id);
    }

    // --------------------------------------------------------------------------

    /**
     * @throws CdnException
     */
    private function linkUrl(int $id, string $linking, string $url): string
    {
        if ($linking === 'CUSTOM' && $url !== '') {
            return $url;
        }
        if ($linking === 'FULLSIZE') {
            return (string) cdnServe($id);
        }

        return '';
    }
}
