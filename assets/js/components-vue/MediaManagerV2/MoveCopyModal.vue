<template>
    <div class="modal-overlay" @click.self="close">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Move / Copy File</h3>
                <button class="close-button" @click="close">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="file-details">
                    <div class="file-preview">
                        <img
                            v-if="object.is_img"
                            :src="object.url.thumb.grid"
                            :alt="object.file.name.human"
                            class="preview-image"
                        />
                        <div v-else class="file-icon">
                            <span>{{ object.file.ext.toUpperCase() }}</span>
                        </div>
                    </div>
                    <div class="file-info">
                        <p class="filename">{{ object.file.name.human }}</p>
                        <div class="file-details-meta">
                            <span class="file-type">{{ object.file.mime }}</span>
                            <span class="file-size">{{ object.file.size.human }}</span>
                        </div>
                        <div class="bucket-container">
                            <span class="bucket">{{ object.bucket.label }}</span>
                        </div>
                    </div>
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
                    <multi-select
                        v-model="selectedBucketId"
                        :options="availableBuckets"
                        title="Bucket"
                        :single-select="true"
                        class="bucket-selector"
                        ref="bucketSelector"
                        @dropdown-toggled="handleBucketSelectorToggle"
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
            </div>
            <div class="modal-footer">
                <button class="cancel-button" @click="close" :disabled="isProcessing">Cancel</button>
                <button class="submit-button" @click="submitForm" :disabled="!isValid || isProcessing">
                    <span v-if="isProcessing" class="loading-spinner"></span>
                    <span v-else>{{ isCopyMode ? 'Copy' : 'Move' }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import MultiSelect from '../MultiSelect.vue'
import axios from 'axios'

export default {
    name: 'MoveCopyModal',
    components: {
        MultiSelect
    },
    props: {
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
            isProcessing: false,
            dropdownOpen: false
        }
    },

    mounted() {
        // Add window resize and scroll event listeners
        window.addEventListener('resize', this.handleResize);
        window.addEventListener('scroll', this.handleScroll, true); // true for capturing phase
    },

    beforeDestroy() {
        // Remove window resize and scroll event listeners
        window.removeEventListener('resize', this.handleResize);
        window.removeEventListener('scroll', this.handleScroll, true);
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

        handleBucketSelectorToggle(isOpen) {
            this.dropdownOpen = isOpen;
            if (isOpen) {
                this.positionDropdown();
                // Add a class to the modal container to ensure it doesn't get clipped
                const modalContainer = this.$el.querySelector('.modal-container');
                if (modalContainer) {
                    modalContainer.classList.add('dropdown-open');
                }
            } else {
                // Remove the class when dropdown is closed
                const modalContainer = this.$el.querySelector('.modal-container');
                if (modalContainer) {
                    modalContainer.classList.remove('dropdown-open');
                }
            }
        },

        handleResize() {
            if (this.dropdownOpen) {
                this.positionDropdown();
            }
        },

        handleScroll() {
            if (this.dropdownOpen) {
                this.positionDropdown();
            }
        },

        positionDropdown() {
            this.$nextTick(() => {
                // Get the position of the bucket selector
                const selectorEl = this.$refs.bucketSelector.$el;
                const rect = selectorEl.getBoundingClientRect();

                // Get the dropdown element
                const dropdownEl = selectorEl.querySelector('.multi-select__dropdown');
                if (dropdownEl) {
                    // Calculate available space below and above
                    const spaceBelow = window.innerHeight - rect.bottom;
                    const spaceAbove = rect.top;
                    const dropdownHeight = 300; // Approximate height of dropdown

                    // Set the position and width of the dropdown
                    if (spaceBelow < dropdownHeight && spaceAbove > dropdownHeight) {
                        // Position above if not enough space below but enough space above
                        dropdownEl.style.top = `${rect.top + window.scrollY - dropdownHeight - 4}px`;
                        dropdownEl.classList.add('open-upwards');
                    } else {
                        // Position below (default)
                        dropdownEl.style.top = `${rect.bottom + window.scrollY + 4}px`;
                        dropdownEl.classList.remove('open-upwards');
                    }

                    // Check if dropdown would extend beyond right edge of viewport
                    const rightEdge = rect.left + rect.width;
                    const viewportWidth = window.innerWidth;

                    if (rightEdge > viewportWidth) {
                        // Align to right edge of viewport with some padding
                        dropdownEl.style.left = `${viewportWidth - rect.width - 10 + window.scrollX}px`;
                    } else {
                        dropdownEl.style.left = `${rect.left + window.scrollX}px`;
                    }

                    dropdownEl.style.width = `${rect.width}px`;
                }
            });
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
    }
}
</script>

<style lang="scss" scoped>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.modal-container {
    background: white;
    border-radius: 8px;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    overflow: visible; /* Changed from hidden to visible to allow dropdowns to extend beyond */

    &.dropdown-open {
        /* Ensure the modal doesn't clip the dropdown when it's open */
        overflow: visible !important;
        z-index: auto !important;
    }
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;

    h3 {
        margin: 0;
        font-size: 0.875rem;
        font-weight: 600;
        color: #4b5563;
    }

    .close-button {
        background: none;
        border: none;
        padding: 0.25rem;
        cursor: pointer;
        color: #6b7280;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;

        &:hover {
            background: #e5e7eb;
            color: #4b5563;
        }

        svg {
            width: 1rem;
            height: 1rem;
        }
    }
}

.modal-body {
    padding: 1rem;
    max-height: calc(100vh - 200px);
    overflow-y: auto;
    overflow-x: visible; /* Allow dropdowns to extend beyond the modal body */
    position: relative; /* Create a new stacking context */
}

.modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
}

.cancel-button {
    padding: 0.5rem 1rem;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    color: #4b5563;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;

    &:hover {
        background: #e5e7eb;
        color: #374151;
    }

    &:active {
        background: #d1d5db;
    }

    &:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
}

.submit-button {
    padding: 0.5rem 1rem;
    background: #4f46e5;
    border: 1px solid transparent;
    border-radius: 6px;
    color: white;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;

    &:hover {
        background: #4338ca;
    }

    &:active {
        background: #3730a3;
    }

    &:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
}

.file-details {
    display: flex;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.file-preview {
    width: 80px;
    height: 80px;
    margin-right: 1rem;
    flex-shrink: 0;

    .preview-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 4px;
    }

    .file-icon {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f3f4f6;
        border-radius: 4px;
        color: #6b7280;
        font-size: 1rem;
        font-weight: 600;
    }
}

.file-info {
    flex: 1;
    min-width: 0;

    .filename {
        margin: 0 0 0.5rem;
        font-size: 0.875rem;
        color: #111827;
        word-break: break-word;
        font-weight: 500;
    }

    .file-details-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin: 0 0 0.5rem 0;
        font-size: 0.75rem;

        .file-type, .file-size {
            color: #6b7280;
        }
    }

    .bucket-container {
        margin-top: 0.25rem;

        .bucket {
            background: #f0f1f4;
            padding: 2px 6px;
            border-radius: 4px;
            color: #4b5563;
            display: inline-block;
            font-size: 0.75rem;
        }
    }
}

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
