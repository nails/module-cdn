<template>
    <ModalBase
        :visible="visible"
        title="Move File"
        @close="close"
    >
        <div v-if="object">
            <FilePreview
                :file="object"
                :show-checkbox="false"
                :show-date="false"
                :show-border="true"
                :margin-bottom="true"
            />
        </div>
        <form-group
            label="Destination Bucket"
        >
            <bucket-selector
                v-model="selectedBucketId"
                :buckets="availableBuckets"
                placeholder="Select destination bucket"
                :single-select="true"
            />
        </form-group>
        <Status
            type="warning"
            message="Moving will maintain existing relationships, but create a new URL for the file. No redirects will be added from the old URL."
        />
        <Status
            v-if="error"
            type="error"
            :message="error"
        />
        <Status
            v-if="success"
            type="success"
            :message="success"
        />
        <template #footer>
            <div
                :class="{
                    'safari-repaint-fix': true,
                    'run-fix': isProcessing
                }"
            >
                <Button
                    variant="secondary"
                    text="Cancel"
                    icon="cancel"
                    @click="close"
                    :disabled="isProcessing"
                />
                <Button
                    variant="primary"
                    text="Move"
                    icon="move"
                    @click="submitForm"
                    :disabled="!isValid || isProcessing"
                    :loading="isProcessing"
                />
            </div>
        </template>
    </ModalBase>
</template>

<script>
import BucketSelector from '../BucketSelector.vue'
import ModalBase from './ModalBase.vue'
import Button from '../Button.vue'
import FilePreview from '../FilePreview.vue'
import Status from '../Status.vue'
import axios from 'axios'
import FormGroup from "../Form/Group.vue";

export default {
    name: 'ModalMove',
    components: {
        FormGroup,
        BucketSelector,
        ModalBase,
        Button,
        FilePreview,
        Status
    },
    inject: ['cdnApi'],
    props: {
        visible: {
            type: Boolean,
            default: false
        },
        object: {
            type: Object,
            required: true
        },
        buckets: {
            type: Array,
            required: true
        }
    },
    data() {
        return {
            selectedBucketId: null,
            error: null,
            success: null,
            isProcessing: false
        }
    },
    computed: {
        availableBuckets() {
            return this.buckets;
        },
        isValid() {
            return this.selectedBucketId !== null;
        }
    },
    methods: {
        close() {
            this.$emit('close');
        },
        async submitForm() {
            if (!this.isValid) {
                return;
            }

            this.isProcessing = true;
            this.error = null;
            this.success = null;

            try {

                const endpoint = this.cdnApi.object.move();
                const response = await axios.post(endpoint, {
                    object_id: this.object.id,
                    bucket_id: Array.isArray(this.selectedBucketId) ? this.selectedBucketId[0] : this.selectedBucketId,
                });

                this.success = 'File moved successfully!';

                // Emit success event to parent component
                this.$emit('success', response.data.data);

                // Close modal after a delay
                setTimeout(() => {
                    this.close();
                }, 1500);
            } catch (error) {
                console.error('Error during move operation:', error);
                this.error = error.response?.data?.error ||
                    `Failed to move file. Please try again.`;
            } finally {
                this.isProcessing = false;
            }
        }
    },
    watch: {
        visible(newVal) {
            if (newVal) {
                this.selectedBucketId = null;
                this.error = null;
                this.success = null;
            }
        }
    }
}
</script>

<style lang="scss" scoped>

/* Custom styles for the bucket selector dropdown */
.bucket-selector {
    position: static; /* Override relative positioning */

    :deep(.multi-select__dropdown) {
        position: fixed; /* Use fixed positioning instead of absolute */
        width: auto; /* Width will be set by JavaScript */
        z-index: 1100; /* Ensure it's above the modal */

        &.open-upwards {
            /* Styles for dropdown when positioned above */
            .options-list {
                flex-direction: column-reverse;
            }

            .actions {
                order: -1; /* Move actions to the top */
            }
        }
    }
}
</style>
