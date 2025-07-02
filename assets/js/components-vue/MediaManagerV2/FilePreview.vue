<template>
    <div 
        class="file-preview" 
        :class="[
            containerClass,
            { 'no-border': !showBorder, 'with-margin': marginBottom }
        ]"
    >
        <!-- Checkbox (optional) -->
        <div v-if="showCheckbox" class="file-preview-checkbox" @click.stop>
            <input 
                type="checkbox" 
                :checked="isSelected" 
                @change="$emit('selection-change', $event.target.checked)"
            />
        </div>

        <!-- Thumbnail/Icon -->
        <div class="file-preview-thumbnail">
            <img 
                v-if="file && file.is_img" 
                :src="file.url?.thumb?.list || file.url?.thumb?.grid" 
                :alt="fileName" 
                class="thumbnail"
            />
            <div v-else class="file-icon">
                <span>{{ fileExtension }}</span>
            </div>
        </div>

        <!-- File Details -->
        <div class="file-preview-details">
            <div class="file-preview-name">{{ fileName }}</div>
            <div class="file-preview-meta">
                <span class="file-preview-type">{{ fileType }}</span>
                <span class="file-preview-separator">•</span>
                <span class="file-preview-size">{{ fileSize }}</span>
                <span v-if="showDate" class="file-preview-separator">•</span>
                <span v-if="showDate" class="file-preview-date">{{ fileDate }}</span>
            </div>
            <div v-if="showBucket" class="file-preview-bucket">{{ bucketName }}</div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'FilePreview',
    props: {
        file: {
            type: Object,
            required: true
        },
        showCheckbox: {
            type: Boolean,
            default: false
        },
        isSelected: {
            type: Boolean,
            default: false
        },
        showDate: {
            type: Boolean,
            default: true
        },
        showBucket: {
            type: Boolean,
            default: true
        },
        containerClass: {
            type: String,
            default: ''
        },
        showBorder: {
            type: Boolean,
            default: true
        },
        marginBottom: {
            type: Boolean,
            default: true
        }
    },
    emits: ['selection-change'],
    computed: {
        fileName() {
            // Handle null/undefined file
            if (!this.file) {
                return 'No file selected';
            }
            
            // Handle different object structures
            if (this.file.file?.name?.human) {
                return this.file.file.name.human;
            }
            if (this.file.filename_display) {
                return this.file.filename_display;
            }
            if (this.file.filename) {
                return this.file.filename;
            }
            return 'Unknown file';
        },
        fileExtension() {
            if (!this.file) {
                return 'N/A';
            }
            
            if (this.file.file?.ext) {
                return this.file.file.ext.toUpperCase();
            }
            if (this.file.ext) {
                return this.file.ext.toUpperCase();
            }
            return 'FILE';
        },
        fileType() {
            if (!this.file) {
                return 'Unknown type';
            }
            
            if (this.file.group) {
                return this.file.group;
            }
            if (this.file.file?.mime) {
                return this.file.file.mime;
            }
            if (this.file.mime) {
                return this.file.mime;
            }
            return 'Unknown type';
        },
        fileSize() {
            if (!this.file) {
                return 'Unknown size';
            }
            
            if (this.file.file?.size?.human) {
                return this.file.file.size.human;
            }
            if (this.file.size?.human) {
                return this.file.size.human;
            }
            if (this.file.size) {
                return this.formatFileSize(this.file.size);
            }
            return 'Unknown size';
        },
        fileDate() {
            if (!this.file) {
                return '';
            }
            
            if (this.file.created?.formatted) {
                return this.file.created.formatted;
            }
            if (this.file.created_at) {
                return this.file.created_at;
            }
            return '';
        },
        bucketName() {
            if (!this.file) {
                return 'Unknown bucket';
            }
            
            if (this.file.bucket?.label) {
                return this.file.bucket.label;
            }
            if (this.file.bucket?.name) {
                return this.file.bucket.name;
            }
            return 'Unknown bucket';
        }
    },
    methods: {
        formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
        }
    }
}
</script>

<style lang="scss" scoped>
.file-preview {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    transition: all 0.2s ease;
    
    &.with-margin {
        margin-bottom: 1.25rem;
    }

    &:hover {
        background-color: #f9fafb;
        border-color: #d1d5db;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    &.clickable {
        cursor: pointer;
    }

    &.selected {
        background-color: #eef2ff;
        border: 1px solid #c7d2fe;
        box-shadow: 0 2px 4px rgba(99, 102, 241, 0.1);
    }

    &.no-border {
        border: none;
        background: transparent;
        padding: 0.5rem 0;

        &:hover {
            background-color: #f9fafb;
            border: none;
            box-shadow: none;
        }

        &.selected {
            background-color: #eef2ff;
            border: none;
            box-shadow: none;
        }
    }
}

.file-preview-checkbox {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;

    input[type="checkbox"] {
        width: 1rem;
        height: 1rem;
        cursor: pointer;
    }
}

.file-preview-thumbnail {
    width: 60px;
    height: 60px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;

    .thumbnail {
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
        font-size: 0.875rem;
        font-weight: 600;
    }
}

.file-preview-details {
    flex: 1;
    min-width: 0;

    .file-preview-name {
        font-size: 0.875rem;
        font-weight: 500;
        color: #111827;
        margin-bottom: 0.25rem;
        word-break: break-word;
        line-height: 1.3;
    }

    .file-preview-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem;
        margin-bottom: 0.25rem;
        font-size: 0.75rem;
        color: #6b7280;
        line-height: 1.2;

        .file-preview-type,
        .file-preview-size,
        .file-preview-date {
            color: #6b7280;
        }

        .file-preview-separator {
            color: #d1d5db;
        }
    }

    .file-preview-bucket {
        font-size: 0.75rem;
        color: #4b5563;
        background: #f0f1f4;
        padding: 2px 6px;
        border-radius: 4px;
        display: inline-block;
    }
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .file-preview {
        padding: 0.5rem;
        gap: 0.5rem;
    }

    .file-preview-thumbnail {
        width: 50px;
        height: 50px;
    }

    .file-preview-details {
        .file-preview-name {
            font-size: 0.8rem;
        }

        .file-preview-meta {
            font-size: 0.7rem;
        }
    }
}
</style> 