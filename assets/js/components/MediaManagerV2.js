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

                // Get user can create bucket permission
                const userCanCreateBucket = mountPoint ? mountPoint.dataset.userCanCreateBucket === 'true' : false;

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
                            userCanCreateBucket
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
