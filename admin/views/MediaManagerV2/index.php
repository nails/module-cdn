<?php

/** @var \Nails\Common\Service\Input $oInput */
$oInput = \Nails\Factory::service('Input');

if ($oInput::get('isModal')) {

    $switchUrl = siteUrl('admin/cdn/mediaManagerV2/unset_default');

    if ($oInput::get()) {
        $switchUrl .= '?' . http_build_query($oInput::get());
    }
}

?>
<div id="nails-module-cdn-media-manager-v2"
     data-switch-back-url="<?=$switchUrl ?? ''?>"
     data-max-upload-size="<?=maxUploadSize(false)?>"
     data-user-can-create-bucket="<?=json_encode(userHasPermission('admin:cdn:manager:bucket:create'))?>"
>
    Loading Media Manager...
</div>
