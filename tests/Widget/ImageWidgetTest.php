<?php

namespace {
    if (!function_exists('cdnServe')) {
        function cdnServe($id)
        {
            return 'serve:' . $id;
        }
    }

    if (!function_exists('cdnCrop')) {
        function cdnCrop($id, $width, $height)
        {
            if (\Tests\Cdn\Widget\ImageWidgetTest::$reject) {
                throw new \Nails\Cdn\Exception\CdnException('rejected');
            }

            return 'crop:' . $id . ':' . $width . 'x' . $height;
        }
    }

    if (!function_exists('cdnScale')) {
        function cdnScale($id, $width, $height)
        {
            return 'scale:' . $id . ':' . $width . 'x' . $height;
        }
    }
}

namespace Tests\Cdn\Widget {

use Nails\Cdn\Cms\Widgets\Image;
use Nails\Cms\WidgetEditor\Service\Registry;
use PHPUnit\Framework\TestCase;

class ImageWidgetTest extends TestCase
{
    public static bool $reject = false;

    protected function setUp(): void
    {
        parent::setUp();
        self::$reject = false;

        if (!class_exists(\Nails\Cms\WidgetEditor\Widget\Base::class)) {
            $this->markTestSkipped('module-cms is required to test the v2 image widget');
        }
    }

    public function testCdnMonitorWatchesTheImageId(): void
    {
        $this->assertSame(['iImageId'], $this->widget()->getCdnMonitorPaths());
    }

    public function testSizeOptionsGroupLandscapePortraitAndSquare(): void
    {
        $options = Image::sizeOptions([
            (object) ['width' => 400, 'height' => 400],
            (object) ['width' => 800, 'height' => 600],
            (object) ['width' => 300, 'height' => 500],
        ]);

        $this->assertSame(
            [
                ''        => 'Choose a size',
                '800x600' => '800px by 600px',
                '300x500' => '300px by 500px',
                '400x400' => '400px by 400px',
            ],
            $options
        );
    }

    public function testUpgradeCopiesLegacyKeysAndLeavesACurrentShapeAlone(): void
    {
        $widget = $this->widget();

        $upgraded = $widget->upgrade([
            'image_id'  => '42',
            'scaling'   => 'CROP',
            'size'      => '800x600',
            'linking'   => 'CUSTOM',
            'url'       => 'https://example.com',
            'target'    => '_blank',
            'img_attr'  => 'class="x"',
            'link_attr' => 'rel="x"',
        ]);

        $this->assertSame(
            [
                'iImageId'  => '42',
                'sScaling'  => 'CROP',
                'sSize'     => '800x600',
                'sLinking'  => 'CUSTOM',
                'sUrl'      => 'https://example.com',
                'sTarget'   => '_blank',
                'sImgAttr'  => 'class="x"',
                'sLinkAttr' => 'rel="x"',
            ],
            $upgraded
        );
        $this->assertSame($upgraded, $widget->upgrade($upgraded));
    }

    public function testUpgradePrefersTheCurrentKey(): void
    {
        $data = $this->widget()->upgrade(['iImageId' => 7, 'image_id' => 9]);

        $this->assertSame(7, $data['iImageId']);
        $this->assertArrayNotHasKey('image_id', $data);
    }

    public function testUpgradeMapsAPermittedWidthAndHeightAndKeepsAnyOther(): void
    {
        $widget = $this->widget();

        $this->assertSame('800x600', $widget->upgrade(['width' => '800', 'height' => 600])['sSize']);
        $this->assertArrayNotHasKey('width', $widget->upgrade(['width' => 800, 'height' => 600]));

        $kept = $widget->upgrade(['width' => 10, 'height' => 10]);
        $this->assertSame('', $kept['sSize'] ?? '');
        $this->assertSame(10, $kept['width']);
        $this->assertSame(10, $kept['height']);
    }

    public function testRenderCropsScalesAndServes(): void
    {
        $widget = $this->widget();

        $crop = $widget->render([
            'iImageId' => 4,
            'sScaling' => 'CROP',
            'sSize'    => '800x600',
            'sImgAlt'  => 'A & B',
        ]);
        $this->assertStringContainsString('src="crop:4:800x600"', $crop);
        $this->assertStringContainsString('alt="A &amp; B"', $crop);
        $this->assertStringNotContainsString('<a ', $crop);

        $scale = $widget->render(['iImageId' => 4, 'sScaling' => 'SCALE', 'sSize' => '800x600']);
        $this->assertStringContainsString('src="scale:4:800x600"', $scale);

        $full = $widget->render(['iImageId' => 4, 'sScaling' => 'NONE']);
        $this->assertStringContainsString('src="serve:4"', $full);
        $this->assertStringContainsString('cms-widget-image--left', $full);
        $this->assertStringContainsString('style="text-align:left"', $full);
    }

    public function testRenderAlignsLeftCenterOrRight(): void
    {
        $widget = $this->widget();
        $image  = ['iImageId' => 4, 'sScaling' => 'NONE'];

        $center = $widget->render($image + ['sAlignment' => 'center']);
        $this->assertStringContainsString('cms-widget-image--center', $center);
        $this->assertStringContainsString('style="text-align:center"', $center);

        $right = $widget->render($image + ['sAlignment' => 'right']);
        $this->assertStringContainsString('cms-widget-image--right', $right);
        $this->assertStringContainsString('style="text-align:right"', $right);

        $other = $widget->render($image + ['sAlignment' => 'middle']);
        $this->assertStringContainsString('cms-widget-image--left', $other);
        $this->assertStringNotContainsString('middle', $other);
    }

    public function testRenderLinksToFullsizeOrACustomUrl(): void
    {
        $widget = $this->widget();

        $full = $widget->render([
            'iImageId'  => 4,
            'sScaling'  => 'NONE',
            'sLinking'  => 'FULLSIZE',
            'sTarget'   => '_blank',
            'sLinkAttr' => 'rel="noopener"',
        ]);
        $this->assertStringContainsString('href="serve:4"', $full);
        $this->assertStringContainsString('target="_blank"', $full);
        $this->assertStringContainsString('rel="noopener"', $full);

        $custom = $widget->render([
            'iImageId' => 4,
            'sScaling' => 'NONE',
            'sLinking' => 'CUSTOM',
            'sUrl'     => 'https://example.com',
        ]);
        $this->assertStringContainsString('href="https://example.com"', $custom);

        $empty = $widget->render(['iImageId' => 4, 'sScaling' => 'NONE', 'sLinking' => 'CUSTOM', 'sUrl' => '']);
        $this->assertStringNotContainsString('<a ', $empty);
    }

    public function testRenderIsEmptyWithoutAnImageOrANeededSize(): void
    {
        $widget = $this->widget();

        $this->assertSame('', $widget->render([]));
        $this->assertSame('', $widget->render(['iImageId' => 4, 'sScaling' => 'CROP']));
        $this->assertSame('', $widget->render(['iImageId' => 4, 'sScaling' => 'SCALE', 'sSize' => '']));
    }

    public function testRenderIsEmptyWhenTheCdnRejectsTheObject(): void
    {
        self::$reject = true;

        $this->assertSame(
            '',
            $this->widget()->render(['iImageId' => 4, 'sScaling' => 'CROP', 'sSize' => '800x600'])
        );
    }

    public function testPreviewAsksForWhatItCannotDraw(): void
    {
        $widget = $this->preview();

        $this->assertStringContainsString('Choose an image in the inspector.', $widget->render([]));
        $this->assertStringContainsString(
            'Choose a size in the inspector.',
            $widget->render(['iImageId' => 4, 'sScaling' => 'CROP'])
        );

        self::$reject = true;
        $this->assertStringContainsString(
            'This image could not be loaded.',
            $widget->render(['iImageId' => 4, 'sScaling' => 'CROP', 'sSize' => '800x600'])
        );
    }

    private function widget(): Image
    {
        return new class extends Image {
            protected function permittedDimensions(): array
            {
                return [
                    (object) ['width' => 800, 'height' => 600],
                    (object) ['width' => 400, 'height' => 400],
                ];
            }
        };
    }

    private function preview(): Image
    {
        $widget = $this->widget();
        $widget->attach(new Registry(), 'image', Registry::MODE_PREVIEW, 0, []);

        return $widget;
    }
}
}
