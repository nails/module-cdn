<template>
    <ModalBase
        :visible="visible"
        title="Replace File"
        @close="close"
    >
        <div v-if="!object" class="no-object-message">
            <p>No file selected for replacement.</p>
        </div>
        <div v-else>
            <FilePreview
                :file="object"
                :show-checkbox="false"
                :show-date="false"
                :margin-bottom="true"
            />
            <p>
                Replace the file identified above with a file of the same type. This process will ensure that all references to the old file are maintained.
            </p>
            <Status
                type="warning"
                :message="'Be aware that intermediate systems may cache the old file for a period of time.'"
            />
            <div
                class="upload-zone"
                :class="{
                        'drag-over': isDragging,
                        'has-file': selectedFile,
                        'error': error
                    }"
                @dragenter.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @dragover.prevent
                @drop.prevent="handleDrop"
                @click="triggerFileInput"
            >
                <input
                    type="file"
                    ref="fileInput"
                    class="hidden"
                    @change="handleFileSelect"
                />
                <div v-if="!selectedFile" class="upload-prompt">
                    <div class="upload-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                    </div>
                    <div class="upload-text">
                        <p class="primary-text">Drag and drop files here or click to browse</p>
                        <p class="secondary-text">Max file size: {{ formatFileSize(maxUploadSize) }} · Only {{ object?.file?.ext?.toUpperCase() || 'FILE' }} files are allowed</p>
                    </div>
                </div>
                <div v-else class="selected-file">
                    <div class="file-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div class="file-info">
                        <p class="filename">{{ selectedFile.name }}</p>
                        <p class="file-size">{{ formatFileSize(selectedFile.size) }}</p>
                    </div>
                </div>
            </div>

            <Status
                v-if="error"
                type="error"
                :message="error"
            />
            <Status
                v-if="success"
                type="success"
                message="File replaced successfully!"
            />
        </div>

        <template #footer>
            <Button
                variant="secondary"
                text="Cancel"
                icon="cancel"
                @click="close"
                :disabled="isUploading"
            />
            <Button
                variant="primary"
                :text="isUploading ? 'Uploading...' : 'Replace File'"
                icon="restore"
                @click="uploadFile"
                :disabled="!selectedFile || isUploading"
                :loading="isUploading"
            />
        </template>
    </ModalBase>
</template>

<script>
import ModalBase from './ModalBase.vue'
import Button from '../Button.vue'
import FilePreview from '../FilePreview.vue'
import Status from '../Status.vue'

export default {
    name: 'ModalReplaceFile',
    components: {
        ModalBase,
        Button,
        FilePreview,
        Status
    },

    props: {
        visible: {
            type: Boolean,
            default: false
        },
        object: {
            type: Object,
            required: false,
            default: null
        },
        siteUrl: {
            type: String,
            required: true
        },
        maxUploadSize: {
            type: Number,
            required: true
        }
    },

    data() {
        return {
            isDragging: false,
            selectedFile: null,
            error: null,
            success: false,
            isUploading: false
        }
    },

    methods: {
        close() {
            this.$emit('close');
        },

        triggerFileInput() {
            this.$refs.fileInput.click();
        },

        handleDrop(event) {
            this.isDragging = false;
            const file = event.dataTransfer.files[0];
            this.validateAndSetFile(file);
        },

        handleFileSelect(event) {
            const file = event.target.files[0];
            this.validateAndSetFile(file);
        },

        validateAndSetFile(file) {
            this.error = null;

            if (!file) return;

            const fileExtension = file.name.split('.').pop().toLowerCase();
            const allowedExtension = this.object?.file?.ext?.toLowerCase();

            if (!allowedExtension) {
                this.error = 'Unable to determine file type. Please try again.';
                this.selectedFile = null;
                return;
            }

            if (fileExtension !== allowedExtension) {
                this.error = `Only ${allowedExtension} files are allowed. Selected file is ${fileExtension}`;
                this.selectedFile = null;
                return;
            }

            if (file.size > this.maxUploadSize) {
                this.error = `File size exceeds the maximum allowed size of ${this.formatFileSize(this.maxUploadSize)}`;
                this.selectedFile = null;
                return;
            }

            this.selectedFile = file;
        },

        formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
        },

        async uploadFile() {
            if (!this.selectedFile) return;

            this.isUploading = true;
            this.error = null;
            this.success = false;

            const formData = new FormData();
            formData.append('object_id', this.object?.id);
            formData.append('file', this.selectedFile);

            try {
                const response = await fetch(`${this.siteUrl}api/cdn/mediaManagerV2/replace`, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Failed to replace file');
                }

                this.success = true;
                this.$emit('file-replaced', data.data);

                // Close modal after showing success message
                setTimeout(() => {
                    this.close();
                }, 1500);
            } catch (error) {
                this.error = error.message;
            } finally {
                this.isUploading = false;
            }
        }
    }
}
</script>

<style lang="scss" scoped>

/* Modal body content styling */
p {
    color: #6b7280;
    font-size: 0.875rem;
    margin-bottom: 1rem;
    line-height: 1.5;
}

.hidden {
    display: none;
}

.upload-zone {
    border: 2px dashed #e5e7eb;
    border-radius: 6px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #f9fafb;
    margin-top: 1rem;

    &:hover {
        border-color: #6b7280;
        background: #f3f4f6;
    }

    &.drag-over {
        border-color: #4f46e5;
        background: #eef2ff;
    }

    &.has-file {
        border-style: solid;
        border-color: #10b981;
        background: #ecfdf5;
    }

    &.error {
        border-color: #ef4444;
        background: #fef2f2;
    }
}

.upload-prompt {
    .upload-icon {
        margin-bottom: 1rem;

        svg {
            width: 3rem;
            height: 3rem;
            color: #6b7280;
            margin: 0 auto;
        }
    }

    .upload-text {
        .primary-text {
            margin: 0;
            color: #374151;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .secondary-text {
            margin: 0.5rem 0 0;
            color: #6b7280;
            font-size: 0.75rem;
        }
    }
}

.selected-file {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;

    .file-icon {
        svg {
            width: 2rem;
            height: 2rem;
            color: #10b981;
        }
    }

    .file-info {
        text-align: left;

        .filename {
            margin: 0;
            color: #111827;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .file-size {
            margin: 0.25rem 0 0;
            color: #6b7280;
            font-size: 0.75rem;
        }
    }
}

.replace-button {
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

    svg {
        width: 1rem;
        height: 1rem;
    }
}

.hidden {
    display: none;
}
</style>
