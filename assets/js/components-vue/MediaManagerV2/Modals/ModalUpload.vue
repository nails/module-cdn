<template>
    <ModalBase
        :visible="visible"
        title="Upload Files"
        @close="handleClose"
    >
        <div
            class="upload-zone"
            :class="{ 'drag-over': dragOver }"
            @click="$refs.fileInput.click()"
            @dragover.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop.prevent="handleFileDrop"
        >
            <div class="upload-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
            </div>
            <div class="upload-text">
                <p class="primary-text">Drag and drop files here or click to browse</p>
                <p class="secondary-text">Max file size: {{ formatFileSize(maxUploadSize) }}</p>
            </div>
            <input
                type="file"
                multiple
                class="hidden"
                @change="handleFileSelect"
                ref="fileInput"
            >
        </div>
        <form-group
            label="Select Bucket"
        >
            <bucket-selector
                v-model="selectedUploadBucket"
                :buckets="buckets"
                placeholder="Select upload bucket"
                :single-select="true"
            />
        </form-group>

        <div class="overall-progress" v-if="isUploading">
            <div class="overall-progress__bar">
                <div class="overall-progress__fill" :style="{ width: overallProgress + '%' }"></div>
            </div>
            <span class="overall-progress__text">{{ Math.round(overallProgress) }}% Complete</span>
        </div>

        <Status
            v-if="uploadError"
            type="error"
            :message="uploadError"
        />
        <Status
            v-if="uploadSuccess"
            type="success"
            message="Files uploaded successfully!"
        />

        <form-group
            :label="`Files to Upload (${filesToUpload.length})`"
            v-if="filesToUpload.length > 0"
        >
            <div class="upload-preview-list">
                <div class="upload-preview-item" v-for="(file, index) in filesToUpload" :key="index">
                    <div class="upload-preview-thumbnail">
                        <img v-if="isImage(file)" :src="getPreviewUrl(file)" :alt="file.name" class="thumbnail" />
                        <div v-else class="file-icon">
                            <span>{{ getFileExtension(file).toUpperCase() }}</span>
                        </div>
                    </div>
                    <div class="upload-preview-details">
                        <div class="upload-preview-name">{{ file.name }}</div>
                        <div class="upload-preview-meta">
                            <span class="upload-preview-type">{{ file.type || 'Unknown type' }}</span>
                            <span class="upload-preview-separator">•</span>
                            <span class="upload-preview-size">{{ formatFileSize(file.size) }}</span>
                        </div>
                        <div class="progress-bar" v-if="isUploading && uploadProgress[index] !== undefined">
                            <div class="progress-bar__fill" :style="{ width: uploadProgress[index] + '%' }"></div>
                            <span class="progress-bar__text">{{ Math.round(uploadProgress[index]) }}%</span>
                        </div>
                    </div>
                    <button class="remove-button" :disabled="isUploading" @click="!isUploading && removeFile(index)">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
        </form-group>

        <template #footer>
            <Button
                variant="secondary"
                text="Cancel"
                icon="cancel"
                @click="handleClose"
                :disabled="isUploading"
            />
            <Button
                variant="primary"
                :text="isUploading ? 'Uploading...' : 'Upload'"
                icon="upload"
                @click="uploadFiles"
                :disabled="filesToUpload.length === 0 || !selectedUploadBucket || isUploading"
                :loading="isUploading"
            />
        </template>
    </ModalBase>
</template>

<script>
import ModalBase from './ModalBase.vue';
import Button from '../Button.vue';
import BucketSelector from '../BucketSelector.vue';
import Status from '../Status.vue';
import FormGroup from "../Form/Group.vue";
import axios from 'axios';

export default {
    name: 'ModalUpload',
    components: {
        FormGroup,
        ModalBase,
        Button,
        BucketSelector,
        Status
    },
    props: {
        visible: {
            type: Boolean,
            default: false
        },
        buckets: {
            type: Array,
            required: true
        },
        maxUploadSize: {
            type: Number,
            required: true
        }
    },
    data() {
        return {
            dragOver: false,
            filesToUpload: [],
            selectedUploadBucket: null,
            isUploading: false,
            uploadProgress: [],
            overallProgress: 0,
            uploadError: null,
            uploadSuccess: false
        };
    },
    methods: {
        handleClose() {
            this.filesToUpload = [];
            this.uploadProgress = [];
            this.overallProgress = 0;
            this.uploadError = null;
            this.uploadSuccess = false;
            this.selectedUploadBucket = null;
            this.$emit('close');
        },
        handleFileSelect(event) {
            const files = Array.from(event.target.files);
            this.filesToUpload.push(...files);
            event.target.value = '';
        },
        handleFileDrop(event) {
            this.dragOver = false;
            const files = Array.from(event.dataTransfer.files);
            this.filesToUpload.push(...files);
        },
        removeFile(index) {
            this.filesToUpload.splice(index, 1);
        },
        formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
        },
        isImage(file) {
            return file.type && file.type.startsWith('image/');
        },
        getPreviewUrl(file) {
            if (!file._previewUrl && this.isImage(file)) {
                file._previewUrl = URL.createObjectURL(file);
            }
            return file._previewUrl;
        },
        getFileExtension(file) {
            const parts = file.name.split('.');
            return parts.length > 1 ? parts.pop() : 'FILE';
        },
        async uploadFiles() {
            if (this.filesToUpload.length === 0 || !this.selectedUploadBucket) return;
            this.isUploading = true;
            this.uploadError = null;
            this.uploadSuccess = false;
            this.uploadProgress = Array(this.filesToUpload.length).fill(0);
            this.overallProgress = 0;
            try {
                for (let i = 0; i < this.filesToUpload.length; i++) {
                    const file = this.filesToUpload[i];
                    const formData = new FormData();
                    formData.append('upload', file);
                    try {
                        await axios.post(
                            `${window.SITE_URL}api/cdn/object/create`,
                            formData,
                            {
                                headers: {
                                    'Content-Type': 'multipart/form-data',
                                    'X-Cdn-Bucket': this.selectedUploadBucket,
                                    'X-Cdn-Urls': '120x120-crop,400x400-crop'
                                },
                                onUploadProgress: (progressEvent) => {
                                    if (progressEvent.total) {
                                        const percent = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                                        this.$set(this.uploadProgress, i, percent);
                                        // Update overall progress
                                        let total = 0;
                                        for (let j = 0; j < this.uploadProgress.length; j++) {
                                            total += this.uploadProgress[j] || 0;
                                        }
                                        this.overallProgress = total / this.uploadProgress.length;
                                    }
                                }
                            }
                        );
                        this.$set(this.uploadProgress, i, 100);
                    } catch (err) {
                        this.uploadError = `Failed to upload file: ${file.name}`;
                        break;
                    }
                }
                if (!this.uploadError) {
                    this.uploadSuccess = true;
                    this.$emit('upload-success');
                    setTimeout(() => {
                        this.handleClose();
                    }, 1500);
                }
            } catch (e) {
                this.uploadError = 'Failed to upload files.';
            } finally {
                this.isUploading = false;
            }
        }
    }
};
</script>

<style scoped>
.upload-zone {
    border: 2px dashed #e5e7eb;
    border-radius: 6px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #f9fafb;
    margin-bottom: 1rem;
}

.upload-zone.drag-over {
    border-color: #4f46e5;
    background: #eef2ff;
}

.upload-icon {
    margin-bottom: 1rem;

    svg {
        width: 3rem;
        height: 3rem;
        color: #6b7280;
        margin: 0 auto;
    }
}

.upload-text .primary-text {
    margin: 0;
    color: #374151;
    font-size: 0.875rem;
    font-weight: 500;
}

.upload-text .secondary-text {
    margin: 0.5rem 0 0;
    color: #6b7280;
    font-size: 0.75rem;
}

.overall-progress {
    margin-bottom: 1rem;
}

.overall-progress__bar {
    width: 100%;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    margin-bottom: 0.5rem;
}

.overall-progress__fill {
    height: 100%;
    background: #4f46e5;
    border-radius: 4px;
    transition: width 0.2s ease;
}

.overall-progress__text {
    font-size: 0.85rem;
    color: #4b5563;
}

.upload-preview-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 1rem;
}

.upload-preview-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    transition: all 0.2s ease;
    position: relative;
}

.upload-preview-item:hover {
    background-color: #f9fafb;
    border-color: #d1d5db;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.upload-preview-thumbnail {
    width: 60px;
    height: 60px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.upload-preview-thumbnail .thumbnail {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 4px;
}

.upload-preview-thumbnail .file-icon {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f3f4f6;
    border-radius: 4px;
    color: #6b7280;
    font-size: 0.875rem;
    font-weight: 600;
}

.upload-preview-details {
    flex: 1;
    min-width: 0;
}

.upload-preview-name {
    font-size: 0.875rem;
    font-weight: 500;
    color: #111827;
    margin-bottom: 0.25rem;
    word-break: break-word;
    line-height: 1.3;
}

.upload-preview-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    margin-bottom: 0.25rem;
    font-size: 0.75rem;
    color: #6b7280;
    line-height: 1.2;
}

.upload-preview-separator {
    color: #d1d5db;
}

.remove-button {
    background: none;
    border: none;
    color: #b91c1c;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: background 0.2s;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.remove-button svg {
    width: 20px;
    height: 20px;
    display: block;
    margin: 0 auto;
}

.remove-button:hover {
    background: #fee2e2;
}

.remove-button[disabled] {
    opacity: 0.5;
    cursor: not-allowed;
    background: none;
}
</style> 
