<?php

/**
 * This class is the "Image" CMS editor view
 *
 * @package     Nails
 * @subpackage  module-cms
 * @category    Widget
 * @author      Nails Dev Team
 * @link
 */

?>
<div class="fieldset">
    <?php

    echo form_field_cdn_object_picker([
        'key'     => 'iImageId',
        'label'   => 'Image',
        'default' => $iImageId,
    ]);

    echo form_field_dropdown([
        'key'     => 'sScaling',
        'label'   => 'Scaling',
        'class'   => 'select2',
        'default' => $sScaling,
        'options' => [
            'NONE'  => 'None, show fullsize',
            'CROP'  => 'Crop',
            'SCALE' => 'Scale',
        ],
        'info'    => implode('', [
            '<span class="alert alert-info none">The image will be shown at its native size</span>',
            '<span class="alert alert-info crop">When cropping, the image is guaranteed to be the defined dimension</span>',
            '<span class="alert alert-info scale">When scaling, the image is guaranteed not to exceed the defined dimension</span>',
        ]),
    ]);

    echo form_field_dropdown([
        'key'     => 'sSize',
        'label'   => 'Size',
        'class'   => 'select2',
        'default' => $sSize,
        'options' => $aDimensions,
        'info'    => 'These dimensions are configured by the application and are limited to the above for security reasons.',
    ]);

    echo form_field_dropdown([
        'key'     => 'sLinking',
        'label'   => 'Linking',
        'class'   => 'select2',
        'default' => $sLinking,
        'options' => [
            'NONE'     => 'Do not link',
            'FULLSIZE' => 'Link to fullsize',
            'CUSTOM'   => 'Custom URL',
        ],
    ]);

    echo form_field([
        'key'         => 'sUrl',
        'label'       => 'URL',
        'default'     => $sUrl,
        'placeholder' => 'http://www.example.com',
    ]);

    echo form_field_dropdown([
        'key'     => 'sTarget',
        'label'   => 'Target',
        'class'   => 'select2',
        'default' => $sTarget,
        'options' => [
            ''        => 'None',
            '_blank'  => 'New window/tab',
            '_parent' => 'Parent window/tab',
        ],
    ]);

    echo form_field([
        'key'         => 'sImgAttr',
        'label'       => 'Attributes',
        'default'     => $sImgAttr,
        'placeholder' => 'Any additional attributes to include in the image tag.',
    ]);

    echo form_field([
        'key'         => 'sLinkAttr',
        'label'       => 'Link Attributes',
        'default'     => $sLinkAttr,
        'placeholder' => 'Any additional attributes to include in the link tag.',
    ]);

    ?>
</div>
