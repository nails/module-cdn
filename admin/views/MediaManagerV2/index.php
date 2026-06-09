<?php

use Nails\Cdn\Constants;
use Nails\Cdn\Service\Cdn;
use Nails\Cdn\Service\MediaManager;
use Nails\Cdn\Service\Monitor;
use Nails\Common\Service\Input;
use Nails\Factory;

/** @var Input $input */
$input = Factory::service('Input');
/** @var MediaManager $mediaManager */
$mediaManager = Factory::service('MediaManager', Constants::MODULE_SLUG);
/** @var Monitor $oMonitor */
$oMonitor = Factory::service('Monitor', Constants::MODULE_SLUG);
/** @var Cdn $oCdn */
$oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);

$aPermittedDimensions = array_values(array_map(
    fn($o) => ['width' => $o->width, 'height' => $o->height],
    $oCdn->getPermittedDimensions()
));

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
     data-user-can-create-object="<?=json_encode(userHasPermission('admin:cdn:mediamanager:object:create'))?>"
     data-user-can-edit-object="<?=json_encode(userHasPermission('admin:cdn:mediamanager:object:edit'))?>"
     data-user-can-replace-object="<?=json_encode(userHasPermission('admin:cdn:mediamanager:object:replace'))?>"
     data-user-can-move-object="<?=json_encode(userHasPermission('admin:cdn:mediamanager:object:move'))?>"
     data-user-can-copy-object="<?=json_encode(userHasPermission('admin:cdn:mediamanager:object:copy'))?>"
     data-user-can-delete-object="<?=json_encode(userHasPermission('admin:cdn:mediamanager:object:delete'))?>"
     data-user-can-restore-object="<?=json_encode(userHasPermission('admin:cdn:mediamanager:object:restore'))?>"
     data-user-can-purge-object="<?=json_encode(userHasPermission('admin:cdn:mediamanager:object:purge'))?>"
     data-user-can-create-bucket="<?=json_encode(userHasPermission('admin:cdn:mediamanager:bucket:create'))?>"
     data-user-can-edit-bucket="<?=json_encode(userHasPermission('admin:cdn:mediamanager:bucket:edit'))?>"
     data-user-can-delete-bucket="<?=json_encode(userHasPermission('admin:cdn:mediamanager:bucket:delete'))?>"
     data-system-metadata-keys="<?=htmlentities(json_encode($oMonitor->getSystemMetadataKeys()), ENT_QUOTES)?>"
     data-permitted-dimensions="<?=htmlentities(json_encode($aPermittedDimensions), ENT_QUOTES)?>"
>
    Loading Media Manager...
</div>
