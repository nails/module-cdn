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
     data-user-can-create-object="<?=json_encode(userHasPermission('admin:cdn:manager:object:create'))?>"
     data-user-can-edit-object="<?=json_encode(userHasPermission('admin:cdn:manager:object:edit'))?>"
     data-user-can-replace-object="<?=json_encode(userHasPermission('admin:cdn:manager:object:replace'))?>"
     data-user-can-move-object="<?=json_encode(userHasPermission('admin:cdn:manager:object:move'))?>"
     data-user-can-copy-object="<?=json_encode(userHasPermission('admin:cdn:manager:object:copy'))?>"
     data-user-can-delete-object="<?=json_encode(userHasPermission('admin:cdn:manager:object:delete'))?>"
     data-user-can-restore-object="<?=json_encode(userHasPermission('admin:cdn:manager:object:restore'))?>"
     data-user-can-purge-object="<?=json_encode(userHasPermission('admin:cdn:manager:object:restore'))?>"
     data-user-can-create-bucket="<?=json_encode(userHasPermission('admin:cdn:manager:bucket:create'))?>"
     data-user-can-edit-bucket="<?=json_encode(userHasPermission('admin:cdn:manager:bucket:edit'))?>"
     data-user-can-delete-bucket="<?=json_encode(userHasPermission('admin:cdn:manager:bucket:delete'))?>"
>
    Loading Media Manager...
</div>
