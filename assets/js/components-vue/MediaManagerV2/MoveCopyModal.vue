<template>
    <BaseModal
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

        <div class="form-group">
            <label>Action:</label>
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
        </div>

        <div class="form-group">
            <label>Destination Bucket:</label>
            <bucket-selector
                v-model="selectedBucketId"
                :buckets="availableBuckets"
                placeholder="Select destination bucket"
                :single-select="true"
            />
        </div>

        <div v-if="error" class="error-message">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
            {{ error }}
        </div>

        <div v-if="success" class="success-message">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            {{ success }}
        </div>
        
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
    </BaseModal>
</template>

<script>
import BucketSelector from './BucketSelector.vue'
import Button from './Button.vue'
import BaseModal from './BaseModal.vue'
import FilePreview from './FilePreview.vue'
import axios from 'axios'

export default {
    name: 'MoveCopyModal',
    components: {
        BucketSelector,
        Button,
        BaseModal,
        FilePreview
    },
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
        },
        siteUrl: {
            type: String,
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
                    `${this.siteUrl}api/cdn/mediamanagerv2/copy` :
                    `${this.siteUrl}api/cdn/mediamanagerv2/move`;

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

.form-group {
    margin-bottom: 1.25rem;

    label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: #374151;
    }
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

.error-message, .success-message {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
    font-size: 0.875rem;

    svg {
        width: 1.25rem;
        height: 1.25rem;
        flex-shrink: 0;
    }
}

.error-message {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;

    svg {
        color: #ef4444;
    }
}

.success-message {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;

    svg {
        color: #22c55e;
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
    to { transform: rotate(360deg); }
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
