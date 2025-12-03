<?php

use Nails\Cdn\Constants;
use Nails\Cdn\Service\MediaManager;
use Nails\Common\Service\Input;
use Nails\Factory;

/** @var Input $input */
$input = Factory::service('Input');
/** @var MediaManager $mediaManager */
$mediaManager = Factory::service('MediaManager', Constants::MODULE_SLUG);

if ($input::get('isModal') && $mediaManager->isVersionEnabled(Constants::MEDIA_MANAGER_V1)) {
    $switchUrl = $mediaManager->getUrl(
        query: $input::get(),
        path: '/set_default',
        version: Constants::MEDIA_MANAGER_V1
    );
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
