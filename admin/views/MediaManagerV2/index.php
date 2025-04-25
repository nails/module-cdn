<?php

/** @var \Nails\Common\Service\Input $oInput */
$oInput = \Nails\Factory::service('Input');

if ($oInput::get('isModal')) {

    $switchUrl = siteUrl('admin/cdn/mediaManagerV2/unset_default');

    if ($oInput::get()) {
        $switchUrl .= '?' . http_build_query($oInput::get());
    }

    ?>
    <p class="try-new-manager try-new-manager--revert">
        <a href="<?=$switchUrl?>" class="btn btn-primary">Switch</a>
        <span>Go back to the original Media Manager</span>
    </p>
    <?php
}

?>
<div id="nails-module-cdn-media-manager-v2" data-max-upload-size="<?=maxUploadSize(false)?>">
    Loading Media Manager...
</div>
