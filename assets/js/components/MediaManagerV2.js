import MediaManagerV2Vue from '../components-vue/MediaManagerV2.vue';
import ObjectListItem from '../components-vue/MediaManagerV2/ObjectListItem.vue';
import ObjectGridItem from '../components-vue/MediaManagerV2/ObjectGridItem.vue';
import MultiSelect from '../components-vue/MediaManagerV2/MultiSelect.vue';

class MediaManagerV2 {
    constructor(adminController) {
        this.adminController = adminController;
        this.adminController.log('Constructing');
        this.initializeWhenVueReady();
    }

    waitForVue() {
        return new Promise((resolve) => {
            const checkVue = () => {
                if (window.Vue) {
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
                const switchBackUrl = mountPoint ? mountPoint.dataset.switchBackUrl : '';

                // Get max upload size from mount point
                const maxUploadSize = mountPoint ? parseInt(mountPoint.dataset.maxUploadSize) : 10485760; // Default to 10MB if not set

                // User permissions
                const userCanCreateObject = mountPoint ? mountPoint.dataset.userCanCreateObject === 'true' : false;
                const userCanEditObject = mountPoint ? mountPoint.dataset.userCanEditObject === 'true' : false;
                const userCanReplaceObject = mountPoint ? mountPoint.dataset.userCanReplaceObject === 'true' : false;
                const userCanMoveObject = mountPoint ? mountPoint.dataset.userCanMoveObject === 'true' : false;
                const userCanCopyObject = mountPoint ? mountPoint.dataset.userCanCopyObject === 'true' : false;
                const userCanDeleteObject = mountPoint ? mountPoint.dataset.userCanDeleteObject === 'true' : false;
                const userCanRestoreObject = mountPoint ? mountPoint.dataset.userCanRestoreObject === 'true' : false;
                const userCanPurgeObject = mountPoint ? mountPoint.dataset.userCanPurgeObject === 'true' : false;
                const userCanCreateBucket = mountPoint ? mountPoint.dataset.userCanCreateBucket === 'true' : false;
                const userCanEditBucket = mountPoint ? mountPoint.dataset.userCanEditBucket === 'true' : false;
                const userCanDeleteBucket = mountPoint ? mountPoint.dataset.userCanDeleteBucket === 'true' : false;

                // System metadata keys (reserved, read-only in the editor)
                const systemMetadataKeys = mountPoint
                    ? JSON.parse(mountPoint.dataset.systemMetadataKeys || '[]')
                    : [];

                // Create a new Vue instance with all components
                new Vue({
                    el: '#nails-module-cdn-media-manager-v2',
                    components: {
                        MediaManagerV2Vue,
                        ObjectListItem,
                        ObjectGridItem,
                        MultiSelect
                    },
                    render: h => h(MediaManagerV2Vue, {
                        props: {
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
                        }
                    })
                });
            } catch (error) {
                this.adminController.log('Error initializing Vue:', error);
            }
        }
    }
}

export default MediaManagerV2;
