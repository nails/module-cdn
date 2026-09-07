import MediaManagerV2Vue from '../components-vue/MediaManagerV2.vue';

class MediaManagerV2 {
    constructor(adminController) {
        this.adminController = adminController;
        this.adminController.log('Constructing');
        this.initializeWhenVueReady();
    }

    waitForVue() {
        return new Promise((resolve) => {
            const checkVue = () => {
                if (window.Vue && typeof window.Vue.createApp === 'function') {
                    resolve(window.Vue);
                } else {
                    setTimeout(checkVue, 100);
                }
            };
            checkVue();
        });
    }

    async initializeWhenVueReady() {
        const mountPoint = document.querySelector('#nails-module-cdn-media-manager-v2');
        if (mountPoint) {
            try {
                const Vue = await this.waitForVue();
                this.adminController.log('Mounting MediaManagerV2');

                // Get switch back URL from mount point
                const switchBackUrl = mountPoint.dataset.switchBackUrl || '';

                // Get max upload size from mount point
                const maxUploadSize = parseInt(mountPoint.dataset.maxUploadSize) || 10485760; // Default to 10MB if not set

                // User permissions
                const userCanCreateObject = mountPoint.dataset.userCanCreateObject === 'true';
                const userCanEditObject = mountPoint.dataset.userCanEditObject === 'true';
                const userCanReplaceObject = mountPoint.dataset.userCanReplaceObject === 'true';
                const userCanMoveObject = mountPoint.dataset.userCanMoveObject === 'true';
                const userCanCopyObject = mountPoint.dataset.userCanCopyObject === 'true';
                const userCanDeleteObject = mountPoint.dataset.userCanDeleteObject === 'true';
                const userCanRestoreObject = mountPoint.dataset.userCanRestoreObject === 'true';
                const userCanPurgeObject = mountPoint.dataset.userCanPurgeObject === 'true';
                const userCanCreateBucket = mountPoint.dataset.userCanCreateBucket === 'true';
                const userCanEditBucket = mountPoint.dataset.userCanEditBucket === 'true';
                const userCanDeleteBucket = mountPoint.dataset.userCanDeleteBucket === 'true';

                // System metadata keys (reserved, read-only in the editor)
                const systemMetadataKeys = JSON.parse(mountPoint.dataset.systemMetadataKeys || '[]');

                // Permitted dimensions for CKEditor image scaling
                const permittedDimensions = JSON.parse(mountPoint.dataset.permittedDimensions || '[]');

                const { createApp, h } = Vue;
                createApp({
                    render: () => h(MediaManagerV2Vue, {
                        switchBackUrl,
                        maxUploadSize,
                        userCanCreateObject,
                        userCanEditObject,
                        userCanReplaceObject,
                        userCanMoveObject,
                        userCanCopyObject,
                        userCanDeleteObject,
                        userCanRestoreObject,
                        userCanPurgeObject,
                        userCanCreateBucket,
                        userCanEditBucket,
                        userCanDeleteBucket,
                        systemMetadataKeys,
                        permittedDimensions,
                    })
                }).mount('#nails-module-cdn-media-manager-v2');
            } catch (error) {
                this.adminController.log('Error initializing Vue:', error);
            }
        }
    }
}

export default MediaManagerV2;
