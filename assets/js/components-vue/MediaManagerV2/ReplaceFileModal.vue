<template>
    <div class="modal-overlay" @click.self="close">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Replace File</h3>
                <button class="close-button" @click="close">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="current-file-info">
                    <h4>Current File</h4>
                    <p>{{ object.file.name.human }}</p>
                    <p class="file-meta">{{ object.group }} · {{ object.file.ext.toUpperCase() }} · {{ object.file.size.human }}</p>
                </div>

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
                            <p class="secondary-text">Max file size: {{ formatFileSize(maxUploadSize) }} · Only {{ object.file.ext.toUpperCase() }} files are allowed</p>
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
                    File replaced successfully!
                </div>
            </div>
            <div class="modal-footer">
                <button 
                    class="cancel-button" 
                    @click="close"
                    :disabled="isUploading"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                    Cancel
                </button>
                <button 
                    class="replace-button" 
                    @click="uploadFile"
                    :disabled="!selectedFile || isUploading"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                    </svg>
                    {{ isUploading ? 'Uploading...' : 'Replace File' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ReplaceFileModal',
    
    props: {
        object: {
            type: Object,
            required: true
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
            const allowedExtension = this.object.file.ext.toLowerCase();

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
            formData.append('object_id', this.object.id);
            formData.append('file', this.selectedFile);

            try {
                const response = await fetch(`${this.siteUrl}api/cdn/object/replace`, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Failed to replace file');
                }

                this.success = true;
                this.$emit('file-replaced', data.data.object);

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
    z-index: 1000;
}

.modal-container {
    background: white;
    border-radius: 8px;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    overflow: hidden;
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
}

.current-file-info {
    margin-bottom: 1rem;
    padding: 1rem;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;

    h4 {
        margin: 0 0 0.5rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #4b5563;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    p {
        margin: 0;
        font-size: 0.875rem;
        color: #111827;

        &.file-meta {
            color: #6b7280;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
    }
}

.upload-zone {
    border: 2px dashed #e5e7eb;
    border-radius: 6px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #f9fafb;

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

.error-message {
    margin-top: 1rem;
    padding: 0.75rem;
    background: #fef2f2;
    border: 1px solid #fee2e2;
    border-radius: 6px;
    color: #b91c1c;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;

    svg {
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
    }
}

.success-message {
    margin-top: 1rem;
    padding: 0.75rem;
    background: #ecfdf5;
    border: 1px solid #d1fae5;
    border-radius: 6px;
    color: #047857;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;

    svg {
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
    }
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