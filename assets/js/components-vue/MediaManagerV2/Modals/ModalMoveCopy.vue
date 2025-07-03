<template>
    <ModalBase
        :visible="visible"
        title="Move / Copy File"
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
            label="Action"
        >
            <div class="toggle-container">
                <button
                    class="toggle-button"
                    :class="{ active: !isCopyMode }"
                    @click="isCopyMode = false"
                >
                    Move
                </button>
                <button
                    class="toggle-button"
                    :class="{ active: isCopyMode }"
                    @click="isCopyMode = true"
                >
                    Copy
                </button>
            </div>
            <div class="action-explainer">
                <template v-if="isCopyMode">
                    Copying will maintain the old file and create a duplicate in the new location.
                </template>
                <template v-else>
                    Moving will create a new URL for the file. No redirects will be added from the old URL.
                </template>
            </div>
        </form-group>
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
            <Button
                variant="secondary"
                text="Cancel"
                icon="cancel"
                @click="close"
                :disabled="isProcessing"
            />
            <Button
                variant="primary"
                :text="isCopyMode ? 'Copy' : 'Move'"
                icon="move"
                @click="submitForm"
                :disabled="!isValid || isProcessing"
                :loading="isProcessing"
            />
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
    name: 'ModalMoveCopy',
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
            isCopyMode: false,
            selectedBucketId: null,
            error: null,
            success: null,
            isProcessing: false
        }
    },
    computed: {
        availableBuckets() {
            // Filter out the current bucket
            return this.buckets.filter(bucket => bucket.id !== this.object.bucket.id);
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
                const endpoint = this.isCopyMode ?
                    this.cdnApi.object.copy() :
                    this.cdnApi.object.move();

                const response = await axios.post(endpoint, {
                    object_id: this.object.id,
                    bucket_id: Array.isArray(this.selectedBucketId) ? this.selectedBucketId[0] : this.selectedBucketId,
                    action: this.isCopyMode ? 'copy' : 'move'
                });

                this.success = this.isCopyMode ?
                    'File copied successfully!' :
                    'File moved successfully!';

                // Emit success event to parent component
                this.$emit('success', {
                    action: this.isCopyMode ? 'copy' : 'move',
                    object: response.data.data
                });

                // Close modal after a delay
                setTimeout(() => {
                    this.close();
                }, 1500);
            } catch (error) {
                console.error('Error during move/copy operation:', error);
                this.error = error.response?.data?.error ||
                    `Failed to ${this.isCopyMode ? 'copy' : 'move'} file. Please try again.`;
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
                this.isCopyMode = false;
            }
        }
    }
}
</script>

<style lang="scss" scoped>
.action-explainer {
    font-size: 13px;
    color: #6b7280;
    margin-top: 6px;
    line-height: 1.4;
    font-style: italic;
}

.toggle-container {
    display: flex;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    overflow: hidden;
}

.toggle-button {
    flex: 1;
    padding: 0.5rem 1rem;
    border: none;
    background: #f9fafb;
    color: #6b7280;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;

    &:first-child {
        border-right: 1px solid #e5e7eb;
    }

    &.active {
        background: #4f46e5;
        color: white;
    }

    &:hover:not(.active) {
        background: #f3f4f6;
        color: #4b5563;
    }
}

.loading-spinner {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

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
