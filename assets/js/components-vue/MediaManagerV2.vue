<template>
    <div class="nails-module-cdn-media-manager-v2">
        <div class="sidebar">
            <div class="sidebar__filter sidebar__filter--keyword">
                <p class="sidebar__filter__title">Search &amp; Filter</p>
                <input type="text" v-model="keywords" placeholder="Keywords" />
            </div>
            <div class="sidebar__filter sidebar__filter--bucket">
                <p class="sidebar__filter__title">Bucket</p>
                <div class="loading" v-if="loadingBuckets"></div>
                <multi-select
                    v-if="!loadingBuckets && buckets.length > 0"
                    v-model="selectedBuckets"
                    :options="buckets"
                    title="Buckets"
                    ref="bucketFilter"
                    @dropdown-toggled="handleDropdownToggle('bucketFilter')"
                />
            </div>
            <div class="sidebar__filter sidebar__filter--file-type">
                <p class="sidebar__filter__title">File type</p>
                <div class="loading" v-if="loadingFileTypes"></div>
                <multi-select
                    v-if="!loadingFileTypes && fileTypes.length > 0"
                    v-model="selectedFileTypes"
                    :options="fileTypes"
                    title="File Types"
                    ref="fileTypeFilter"
                    @dropdown-toggled="handleDropdownToggle('fileTypeFilter')"
                />
            </div>
            <div class="sidebar__filter sidebar__filter--uploader">
                <p class="sidebar__filter__title">Uploader</p>
                <div class="loading" v-if="loadingUploaders"></div>
                <multi-select
                    v-if="!loadingUploaders && uploaders.length > 0"
                    v-model="selectedUploaders"
                    :options="uploaders"
                    title="Uploaders"
                    sub-label-key="email"
                    ref="uploaderFilter"
                    @dropdown-toggled="handleDropdownToggle('uploaderFilter')"
                />
            </div>
            <div class="sidebar__filter sidebar__filter--date">
                <p class="sidebar__filter__title">Upload date</p>
                <input type="date" v-model="dateLower" />
                <span>to</span>
                <input type="date" v-model="dateUpper" />
            </div>
        </div>
        <div class="body">
            <div class="body__switcher">
                <div class="body__actions">
                    <button class="upload-button" @click="openUploadModal">
                        <span class="upload-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                        </span>
                        Upload Files
                    </button>
                    <div class="view-toggle">
                        <button 
                            :class="{ active: viewMode === 'list' }" 
                            @click="viewMode = 'list'"
                        >
                            List
                        </button>
                        <button 
                            :class="{ active: viewMode === 'grid' }" 
                            @click="viewMode = 'grid'"
                        >
                            Grid
                        </button>
                    </div>
                </div>
            </div>
            <div class="body__objects">
                <div
                    class="body__objects__loading"
                    v-show="this.loadingObjects"
                >
                    <div class="loading-spinner"></div>
                </div>
                <div
                    class="body__objects__list"
                    v-show="!this.loadingObjects && viewMode === 'list'"
                >
                    <div v-if="objects.length === 0" class="empty-state">
                        <p>No objects found matching your criteria</p>
                    </div>
                    <div v-else class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th class="id-cell" colspan="2">ID</th>
                                    <th class="file-name-cell">Filename &amp; Bucket</th>
                                    <th class="file-type-cell">Type</th>
                                    <th class="file-size-cell">Size</th>
                                    <th class="uploader-cell">Uploader</th>
                                    <th class="created-cell">Created</th>
                                    <th class="actions-cell">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <object-list-item 
                                    v-for="item in objects" 
                                    :key="item.id" 
                                    :item="item"
                                    @action="handleObjectAction"
                                />
                                <tr class="table-load-more" v-if="meta?.pagination?.next && !loadingMoreObjects">
                                    <td colspan="8">
                                        <button
                                            class="btn btn-secondary"
                                            @click="loadMoreObjects"
                                        >
                                            Load More
                                        </button>
                                    </td>
                                </tr>
                                <tr class="table-loading-more" v-if="loadingMoreObjects">
                                    <td colspan="8">
                                        <div class="loading-spinner"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div
                    class="body__objects__grid"
                    v-show="!this.loadingObjects && viewMode === 'grid'"
                >
                    <div v-if="objects.length === 0" class="empty-state">
                        <p>No objects found matching your criteria</p>
                    </div>
                    <div v-else>
                        <div class="grid-container">
                            <object-grid-item
                                v-for="item in objects"
                                :key="item.id"
                                :item="item"
                                @action="handleObjectAction"
                            />
                            <div class="grid-load-more" v-if="meta?.pagination?.next && !loadingMoreObjects">
                                <button
                                    class="btn btn-secondary"
                                    @click="loadMoreObjects"
                                >
                                    Load More
                                </button>
                            </div>
                            <div class="grid-loading-more" v-if="loadingMoreObjects">
                                <div class="loading-spinner"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Modal -->
        <div class="upload-modal" v-if="showUploadModal">
            <div class="upload-modal__overlay" @click="showUploadModal = false"></div>
            <div class="upload-modal__container">
                <div class="upload-modal__header">
                    <h3>Upload Files</h3>
                    <button class="close-button" @click="showUploadModal = false">&times;</button>
                </div>
                <div class="upload-modal__body">
                    <div 
                        class="upload-area" 
                        @click="$refs.fileInput.click()"
                        @dragover.prevent="dragOver = true"
                        @dragleave.prevent="dragOver = false"
                        @drop.prevent="handleFileDrop"
                        :class="{ 'drag-over': dragOver }"
                    >
                        <div class="upload-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="48" height="48">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                        </div>
                        <h4>Drag and drop files here or click to browse</h4>
                        <p>Max file size: 10MB</p>
                        <input 
                            type="file" 
                            multiple 
                            class="file-input" 
                            @change="handleFileSelect"
                            ref="fileInput"
                        >
                    </div>
                    <div class="bucket-selector">
                        <label>Select Bucket:</label>
                        <select v-model="selectedUploadBucket">
                            <option value="">-- Select a bucket --</option>
                            <option v-for="bucket in buckets" :key="bucket.id" :value="bucket.id">
                                {{ bucket.label }}
                            </option>
                        </select>
                    </div>
                    
                    <div class="error-message" v-if="uploadError">
                        {{ uploadError }}
                    </div>
                    
                    <div class="success-message" v-if="uploadSuccess">
                        Files uploaded successfully!
                    </div>

                    <div class="file-list" v-if="filesToUpload.length > 0">
                        <h4>Files to Upload ({{ filesToUpload.length }})</h4>
                        <div class="file-item" v-for="(file, index) in filesToUpload" :key="index">
                            <div class="file-info">
                                <span class="file-name">{{ file.name }}</span>
                                <span class="file-size">{{ formatFileSize(file.size) }}</span>
                            </div>
                            <button class="remove-button" @click="removeFile(index)">&times;</button>
                        </div>
                    </div>
                </div>
                <div class="upload-modal__footer">
                    <button class="cancel-button" @click="showUploadModal = false">Cancel</button>
                    <button 
                        class="upload-button" 
                        @click="uploadFiles" 
                        :disabled="filesToUpload.length === 0 || !selectedUploadBucket || isUploading"
                    >
                        <span v-if="isUploading">Uploading...</span>
                        <span v-else>Upload</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { debounce } from 'lodash'
import MultiSelect from './MultiSelect.vue'
import ObjectListItem from './MediaManagerV2/ObjectListItem.vue'
import ObjectGridItem from './MediaManagerV2/ObjectGridItem.vue'

export default {
    name: 'MediaManagerV2',
    components: {
        MultiSelect,
        ObjectListItem,
        ObjectGridItem
    },
    data() {
        return {
            keywords: null,
            objects: [],
            loadingObjects: true,
            loadingMoreObjects: false,
            buckets: [],
            selectedBuckets: [],
            loadingBuckets: false,
            fileTypes: [],
            selectedFileTypes: [],
            loadingFileTypes: false,
            uploaders: [],
            selectedUploaders: [],
            loadingUploaders: false,
            dateLower: null,
            dateUpper: null,
            viewMode: 'list',
            page: 1,
            meta: null,
            showUploadModal: false,
            filesToUpload: [],
            selectedUploadBucket: null,
            dragOver: false,
            isUploading: false,
            uploadError: null,
            uploadSuccess: false,
        }
    },

    created() {
        this.fetchAllBuckets();
        this.fetchAllFileTypes();
        this.fetchAllUploaders();
        this.debouncedSearch = debounce(this.doSearch, 500);
        this.debouncedSearch();
    },

    watch: {
        keywords() {
            this.debouncedSearch();
        },
        selectedBuckets: {
            handler() {
                this.debouncedSearch();
            }
        },
        selectedFileTypes: {
            handler() {
                this.debouncedSearch();
            }
        },
        selectedUploaders: {
            handler() {
                this.debouncedSearch();
            }
        },
        dateLower() {
            this.debouncedSearch();
        },
        dateUpper() {
            this.debouncedSearch();
        }
    },

    methods: {

        async fetchAllBuckets(url = `${window.SITE_URL}api/cdn/bucket`) {
            try {
                this.loadingBuckets = true;
                this.buckets = await this.iterateOverApiPages(url);
            } catch (error) {
                this.error('Failed to load buckets: ' + error.message);
            } finally {
                this.loadingBuckets = false;
            }
        },

        async fetchAllFileTypes(url = `${window.SITE_URL}api/cdn/mediamanagerv2/filetypes`) {
            try {
                this.loadingFileTypes = true;
                this.fileTypes = await this.iterateOverApiPages(url);
            } catch (error) {
                this.error('Failed to load file types: ' + error.message);
            } finally {
                this.loadingFileTypes = false;
            }

        },

        async fetchAllUploaders(url = `${window.SITE_URL}api/cdn/mediamanagerv2/uploaders`) {
            try {
                this.loadingUploaders = true;
                this.uploaders = await this.iterateOverApiPages(url);
            } catch (error) {
                this.error('Failed to load uploaders: ' + error.message);
            } finally {
                this.loadingUploaders = false;
            }

        },

        async iterateOverApiPages(url, iterableProp = 'data') {
            const response = await axios.get(url);
            let collection = [];

            // Add current page results to our collections array
            if (response.data[iterableProp] && Array.isArray(response.data[iterableProp])) {
                collection = [...collection, ...response.data.data];
            }

            // Check if there's a next page and fetch it
            if (response.data.meta?.pagination?.next) {
                collection = [...collection, ...await this.iterateOverApiPages(response.data.meta.pagination.next)];
            }

            return collection;
        },


        switchView() {
            console.log(`Switched to ${this.viewMode} view`);
        },

        error(message) {
            //  @todo (Pablo 2025-03-05) - Handle this behaviour better, toasts?
            console.log(`Error: ${message}`);
        },

        getFilterValues() {
            return {
                page: this.page,
                keywords: this.keywords,
                buckets: this.selectedBuckets,
                fileTypes: this.selectedFileTypes,
                uploaders: this.selectedUploaders,
                dateLower: this.dateLower,
                dateUpper: this.dateUpper,
            }
        },

        async doSearch() {
            this.loadingObjects = true;
            this.page = 1;
            try {
                console.log('Searching with filters:', this.getFilterValues());
                const response = await axios.get(`${window.SITE_URL}api/cdn/mediamanagerv2/objects`, {
                    params: this.getFilterValues()
                });
                this.objects = response.data.data;
                this.meta = response.data.meta;

            } catch (error) {
                console.error('Loading objects failed:', error);
            } finally {
                this.loadingObjects = false;
            }
        },

        async loadMoreObjects() {
            this.loadingMoreObjects = true;
            this.page += 1;
            try {

                const response = await axios.get(`${window.SITE_URL}api/cdn/mediamanagerv2/objects`, {
                    params: this.getFilterValues()
                });
                this.objects = [...this.objects, ...response.data.data];
                this.meta = response.data.meta;

            } catch (error) {
                console.error('Loading more objects failed:', error);
            } finally {
                this.loadingMoreObjects = false;
            }
        },

        handleObjectAction({ action, item }) {
            switch (action) {
                case 'edit':
                    this.editItem(item);
                    break;
                case 'delete':
                    this.deleteItem(item);
                    break;
                case 'download':
                    this.downloadItem(item);
                    break;
                case 'copy-url':
                    this.copyUrl(item);
                    break;
            }
        },

        editItem(item) {
            console.log('Edit item:', item);
        },

        deleteItem(item) {
            console.log('Delete item:', item);
        },

        downloadItem(item) {
            window.open(item.url.download, '_blank');
        },

        async copyUrl(item) {
            try {
                await navigator.clipboard.writeText(item.url.src);
                // You might want to add a toast notification here
            } catch (err) {
                console.error('Failed to copy URL:', err);
            }
        },

        handleDropdownToggle(currentFilterRef) {
            // Get all filter refs
            const filterRefs = ['bucketFilter', 'fileTypeFilter', 'uploaderFilter'];
            
            // Close all other filters
            filterRefs.forEach(ref => {
                if (ref !== currentFilterRef && this.$refs[ref]) {
                    this.$refs[ref].close();
                }
            });
        },

        handleFileSelect(event) {
            const files = event.target.files;
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                this.filesToUpload.push(file);
            }
            // Reset the input to allow selecting the same file again
            this.$refs.fileInput.value = '';
        },

        removeFile(index) {
            this.filesToUpload.splice(index, 1);
        },

        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        async uploadFiles() {
            if (this.filesToUpload.length === 0 || !this.selectedUploadBucket || this.isUploading) {
                return;
            }

            try {
                this.isUploading = true;
                this.uploadError = null;
                this.uploadSuccess = false;
                
                // Create FormData for the upload
                const formData = new FormData();
                formData.append('bucket_id', this.selectedUploadBucket);
                
                // Add all files to the FormData
                this.filesToUpload.forEach((file, index) => {
                    formData.append(`file${index}`, file);
                });

                // Send the upload request
                const response = await axios.post(`${window.SITE_URL}api/cdn/object`, formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                });

                // Handle successful upload
                console.log('Upload successful:', response.data);
                
                // Show success message
                this.uploadSuccess = true;
                
                // Clear the file list
                this.filesToUpload = [];
                
                // Refresh the object list
                this.doSearch();
                
                // Close the modal after a delay
                setTimeout(() => {
                    this.showUploadModal = false;
                    this.uploadSuccess = false;
                }, 2000);
                
            } catch (error) {
                console.error('Upload failed:', error);
                // Handle upload error
                if (error.response && error.response.data && error.response.data.error) {
                    this.uploadError = error.response.data.error;
                } else {
                    this.uploadError = 'An error occurred during upload. Please try again.';
                }
            } finally {
                this.isUploading = false;
            }
        },

        handleFileDrop(event) {
            this.dragOver = false;
            const files = event.dataTransfer.files;
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                this.filesToUpload.push(file);
            }
        },

        openUploadModal() {
            this.showUploadModal = true;
            this.filesToUpload = [];
            this.selectedUploadBucket = null;
            this.uploadError = null;
            this.uploadSuccess = false;
        },

    }
}
</script>

<style lang="scss" scoped>
.nails-module-cdn-media-manager-v2 {
    display: flex;
    flex-direction: column;
    gap: 0;
    min-height: 100vh;
    background: #f8f9fa;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);

    @media (min-width: 768px) {
        flex-direction: row;
    }

    .loading {
        position: relative;
        height: 1.5rem;
        background-color: #f3f4f6;
        border-radius: 8px;
        overflow: hidden;

        &::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 200%;
            height: 100%;
            background: linear-gradient(
                    90deg,
                    rgba(238, 238, 238, 0) 0%,
                    rgba(246, 246, 246, 0.8) 50%,
                    rgba(238, 238, 238, 0) 100%
            );
            animation: shimmer 2s cubic-bezier(0.4, 0.0, 0.2, 1) infinite;
        }
    }

    @keyframes shimmer {
        0% {
            transform: translateX(-100%);
        }
        100% {
            transform: translateX(50%);
        }
    }

    .sidebar {
        width: 100%;
        border-right: 1px solid #e5e7eb;
        border-top-left-radius: 0.5rem;
        border-bottom-left-radius: 0.5rem;
        background: linear-gradient(to bottom, #f9fafb, #f3f4f6);
        box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.5);

        @media (min-width: 768px) {
            width: 320px;
            flex-shrink: 0;
        }

        &__filter {
            padding: 1.25rem;
            box-sizing: border-box;
            transition: all 0.3s ease;

            &:hover {
                background-color: rgba(255, 255, 255, 0.7);
            }

            &:last-child {
                margin-bottom: 0;
            }

            &__title {
                font-size: 1.125rem;
                font-weight: 600;
                margin-top: 0;
                margin-bottom: 1rem;
                color: #111827;
                display: flex;
                align-items: center;
                
                &::after {
                    content: '';
                    flex: 1;
                    height: 1px;
                    background: linear-gradient(to right, #d1d5db, transparent);
                    margin-left: 12px;
                }
            }

            select,
            input {
                width: 100%;
                margin: 0;
                padding: 10px 14px;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                background: #ffffff;
                box-sizing: border-box;
                font-size: 14px;
                color: #374151;
                transition: all 0.2s ease;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
                
                &:focus {
                    outline: none;
                    border-color: #4f46e5;
                    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
                }
                
                &::placeholder {
                    color: #9ca3af;
                }
            }

            &--date {
                display: grid;
                grid-template-columns: 1fr auto 1fr;
                gap: 10px;
                align-items: center;

                input {
                    width: 100%;
                    margin: 0;
                    padding: 10px 14px;
                }

                span {
                    text-align: center;
                    font-size: 14px;
                    color: #6b7280;
                    font-weight: 500;
                }

                .sidebar__filter__title {
                    grid-column: 1 / -1;
                    margin-bottom: 1rem;
                }
            }

            &--keyword {
                input {
                    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'%3E%3C/path%3E%3C/svg%3E");
                    background-repeat: no-repeat;
                    background-position: 10px center;
                    background-size: 20px;
                    padding-left: 40px;
                }
            }

            &--debug {
                pre {
                    background: #f5f5f5;
                    padding: 10px;
                    border-radius: 4px;
                    overflow-x: auto;
                    white-space: pre-wrap;
                    word-wrap: break-word;
                }
            }
        }
    }

    .body {
        flex-grow: 1;
        background: #ffffff;
        padding: 1rem 0;
        border-top-right-radius: 0.5rem;
        border-bottom-right-radius: 0.5rem;

        &__switcher {
            padding: 0 1rem 1rem 1rem;
            text-align: right;

            .body__actions {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .upload-button {
                display: flex;
                align-items: center;
                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
                color: white;
                border: none;
                border-radius: 20px;
                padding: 10px 18px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 2px 6px rgba(124, 58, 237, 0.2);
                transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                position: relative;
                overflow: hidden;

                &::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: -100%;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
                    transition: 0.5s;
                }

                &:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 3px 10px rgba(124, 58, 237, 0.25);
                    background: linear-gradient(135deg, #4338ca 0%, #6d28d9 100%);
                    
                    &::before {
                        left: 100%;
                    }
                }

                &:active {
                    transform: translateY(1px);
                    box-shadow: 0 1px 4px rgba(124, 58, 237, 0.2);
                }

                .upload-icon {
                    font-size: 18px;
                    margin-right: 8px;
                    background: rgba(255, 255, 255, 0.2);
                    border-radius: 50%;
                    width: 24px;
                    height: 24px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
            }

            .view-toggle {
                display: inline-flex;
                align-items: center;
                background: rgba(255, 255, 255, 0.8);
                padding: 4px;
                border-radius: 20px;
                border: 1px solid rgba(222, 225, 230, 0.6);
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.03), 
                            inset 0 1px 1px rgba(255, 255, 255, 0.7);
                backdrop-filter: blur(4px);

                button {
                    background: none;
                    border: none;
                    padding: 8px 16px;
                    border-radius: 16px;
                    font-size: 14px;
                    color: #5f6368;
                    cursor: pointer;
                    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                    position: relative;
                    z-index: 1;

                    &.active {
                        color: #4f46e5;
                        font-weight: 600;
                    }

                    &.active::before {
                        content: '';
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: white;
                        border-radius: 16px;
                        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                        z-index: -1;
                        animation: scaleIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
                    }

                    &:hover:not(.active) {
                        color: #4f46e5;
                        background-color: rgba(79, 70, 229, 0.05);
                    }
                }

                @keyframes scaleIn {
                    0% {
                        transform: scale(0.8);
                        opacity: 0;
                    }
                    100% {
                        transform: scale(1);
                        opacity: 1;
                    }
                }
            }
        }

        &__objects {
            &__list {
                .table-responsive {
                    overflow-x: auto;
                    -webkit-overflow-scrolling: touch;
                    margin: 0;
                    padding: 0 1rem;
                    border-radius: 12px;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                }

                table {
                    width: 100%;
                    border: 0;
                    border-collapse: collapse;
                    background: #ffffff;
                    font-size: 14px;
                    border-radius: 12px;
                    overflow: hidden;

                    th {
                        padding: 14px 16px;
                        text-align: left;
                        vertical-align: middle;
                        background: linear-gradient(to right, #f9fafb, #f3f4f6);
                        border-bottom: 1px solid #e5e7eb;
                        text-transform: initial;
                        color: #4b5563;
                        font-weight: 600;
                        font-size: 13px;
                        white-space: nowrap;
                        transition: background-color 0.2s ease;

                        &:first-child {
                            border-top-left-radius: 12px;
                        }

                        &:last-child {
                            border-top-right-radius: 12px;
                        }

                        &.file-size-cell {
                            min-width: 100px;
                        }

                        &.uploader-cell {
                            min-width: 150px;
                        }

                        &.actions-cell {
                            min-width: 170px;
                            text-align: center;
                        }
                    }

                    tr:hover td {
                        background-color: rgba(249, 250, 251, 0.8);
                    }

                    .table-load-more,
                    .table-loading-more {
                        td {
                            text-align: center;
                            padding: 16px;
                            background-color: #f9fafb;
                            border-top: 1px dashed #e5e7eb;
                        }
                    }

                    .table-load-more {
                        td {
                            button {
                                min-width: 120px;
                                height: 40px;
                                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
                                color: white;
                                border: none;
                                border-radius: 8px;
                                cursor: pointer;
                                font-weight: 500;
                                transition: all 0.3s ease;
                                box-shadow: 0 2px 4px rgba(79, 70, 229, 0.15);

                                &:hover {
                                    background: linear-gradient(135deg, #4338ca 0%, #6d28d9 100%);
                                    transform: translateY(-1px);
                                    box-shadow: 0 3px 6px rgba(79, 70, 229, 0.2);
                                }

                                &:active {
                                    transform: translateY(1px);
                                }
                            }
                        }
                    }

                    .table-loading-more {
                        td {
                            color: #6b7280;
                            font-size: 14px;
                            position: relative;
                            padding: 16px;
                            text-align: center;

                            .loading-spinner {
                                display: inline-block;
                                width: 30px;
                                height: 30px;
                                border-radius: 50%;
                                border: 2px solid rgba(79, 70, 229, 0.3);
                                border-top-color: #4f46e5;
                                animation: spin 1s linear infinite;
                            }

                            @keyframes spin {
                                to {
                                    transform: rotate(360deg);
                                }
                            }
                        }
                    }
                }
            }

            &__grid {
                .grid-container {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                    gap: 24px;
                    padding: 0 1rem;
                }

                .grid-load-more,
                .grid-loading-more {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background-color: white;
                    border-radius: 12px;
                    border: 1px dashed #e5e7eb;
                    aspect-ratio: 1;
                    padding: 20px;
                    text-align: center;
                    transition: all 0.3s ease;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                }

                .grid-load-more {
                    cursor: pointer;
                    
                    &:hover {
                        border-color: #4f46e5;
                        transform: translateY(-1px);
                        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.08);
                    }
                    
                    button {
                        min-width: 120px;
                        height: 40px;
                        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
                        color: white;
                        border: none;
                        border-radius: 8px;
                        cursor: pointer;
                        font-weight: 500;
                        transition: all 0.3s ease;
                        box-shadow: 0 2px 4px rgba(79, 70, 229, 0.15);

                        &:hover {
                            background: linear-gradient(135deg, #4338ca 0%, #6d28d9 100%);
                            transform: translateY(-1px);
                            box-shadow: 0 3px 6px rgba(79, 70, 229, 0.2);
                        }

                        &:active {
                            transform: translateY(1px);
                        }
                    }
                }

                .grid-loading-more {
                    color: #6b7280;
                    font-size: 14px;
                    position: relative;
                    display: flex;
                    align-items: center;
                    justify-content: center;

                    .loading-spinner {
                        display: inline-block;
                        width: 30px;
                        height: 30px;
                        border-radius: 50%;
                        border: 2px solid rgba(79, 70, 229, 0.3);
                        border-top-color: #4f46e5;
                        animation: spin 1s linear infinite;
                    }
                }

                // Responsive adjustments
                @media (max-width: 768px) {
                    .grid-container {
                        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                        gap: 20px;
                        padding: 0 1rem;
                    }
                }

                @media (max-width: 480px) {
                    .grid-container {
                        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                        gap: 16px;
                        padding: 0 1rem;
                    }
                }
            }

            .empty-state,
            &__loading {
                text-align: center;
                padding: 60px 1rem;
                background: linear-gradient(to bottom, #ffffff, #f9fafb);
                border-radius: 12px;
                color: #6b7280;
                margin: 0 1rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                border: 1px solid #e5e7eb;
                position: relative;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }
            
            .empty-state {
                p {
                    margin: 0;
                    font-size: 16px;
                    font-weight: 500;
                }
            }

            &__loading {
                .loading-spinner {
                    display: inline-block;
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    border: 3px solid rgba(79, 70, 229, 0.3);
                    border-top-color: #4f46e5;
                    animation: spin 1s linear infinite;
                }

                @keyframes spin {
                    to {
                        transform: rotate(360deg);
                    }
                }
            }
        }
    }
}

.upload-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1000;
    display: flex;
    justify-content: center;
    align-items: center;
    animation: modalFadeIn 0.3s ease forwards;

    @keyframes modalFadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    &__overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(15, 23, 42, 0.7);
        backdrop-filter: blur(4px);
        animation: overlayFadeIn 0.3s ease forwards;
    }

    @keyframes overlayFadeIn {
        from {
            backdrop-filter: blur(0);
        }
        to {
            backdrop-filter: blur(4px);
        }
    }

    &__container {
        position: relative;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        background-color: white;
        border-radius: 16px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: containerSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        transform: translateY(20px);
    }

    @keyframes containerSlideUp {
        from {
            transform: translateY(40px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    &__header {
        padding: 20px 24px;
        border-bottom: 1px solid rgba(224, 224, 224, 0.6);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(to right, #f9fafb, #f3f4f6);

        h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #111827;
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .close-button {
            background: none;
            border: none;
            font-size: 22px;
            color: #6b7280;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            transition: all 0.2s ease;

            &:hover {
                background-color: rgba(243, 244, 246, 0.8);
                color: #4f46e5;
                transform: rotate(90deg);
            }
        }
    }

    &__body {
        padding: 24px;
        overflow-y: auto;
        flex: 1;
        background: linear-gradient(to bottom, #ffffff, #f9fafb);

        .upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            padding: 40px 30px;
            text-align: center;
            margin-bottom: 24px;
            transition: all 0.3s ease;
            cursor: pointer;
            background: rgba(249, 250, 251, 0.8);
            position: relative;
            overflow: hidden;

            &::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(135deg, rgba(79, 70, 229, 0.1) 0%, rgba(124, 58, 237, 0.1) 100%);
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            &:hover, &.drag-over {
                border-color: #4f46e5;
                transform: translateY(-2px);
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                
                &::before {
                    opacity: 1;
                }
            }

            &.drag-over {
                background-color: rgba(79, 70, 229, 0.05);
            }

            .upload-icon {
                font-size: 48px;
                color: #6b7280;
                margin-bottom: 16px;
                background: linear-gradient(135deg, #4f46e5, #7c3aed);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                display: inline-block;
                transition: transform 0.3s ease;
            }

            &:hover .upload-icon {
                transform: scale(1.1);
            }

            h4 {
                margin: 0 0 12px;
                font-weight: 600;
                color: #111827;
                font-size: 18px;
            }

            p {
                margin: 0;
                color: #6b7280;
                font-size: 14px;
            }

            .file-input {
                display: none;
            }
        }

        .bucket-selector {
            margin-bottom: 24px;

            label {
                display: block;
                margin-bottom: 10px;
                font-weight: 600;
                color: #111827;
                font-size: 15px;
            }

            select {
                width: 100%;
                padding: 12px 16px;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                font-size: 14px;
                background-color: white;
                transition: all 0.2s ease;
                color: #374151;
                appearance: none;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 12px center;
                background-size: 16px;
                
                &:focus {
                    outline: none;
                    border-color: #4f46e5;
                    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
                }
            }
        }

        .error-message {
            margin-top: 16px;
            padding: 12px 16px;
            background-color: rgba(254, 226, 226, 0.6);
            color: #b91c1c;
            border-radius: 8px;
            border-left: 4px solid #ef4444;
            font-size: 14px;
            animation: messageSlideIn 0.3s ease forwards;
        }
        
        .success-message {
            margin-top: 16px;
            padding: 12px 16px;
            background-color: rgba(220, 252, 231, 0.6);
            color: #15803d;
            border-radius: 8px;
            border-left: 4px solid #22c55e;
            font-size: 14px;
            animation: messageSlideIn 0.3s ease forwards;
        }

        @keyframes messageSlideIn {
            from {
                transform: translateX(-10px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .file-list {
            margin-top: 24px;

            h4 {
                margin: 0 0 16px;
                font-weight: 600;
                color: #111827;
                font-size: 16px;
                display: flex;
                align-items: center;
                
                &::after {
                    content: '';
                    flex: 1;
                    height: 1px;
                    background: linear-gradient(to right, #d1d5db, transparent);
                    margin-left: 12px;
                }
            }

            .file-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 16px;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                margin-bottom: 10px;
                background-color: white;
                transition: all 0.2s ease;
                animation: fileItemAppear 0.3s ease forwards;
                
                &:hover {
                    border-color: #4f46e5;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                    transform: translateY(-1px);
                }

                @keyframes fileItemAppear {
                    from {
                        opacity: 0;
                        transform: translateY(10px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                .file-info {
                    display: flex;
                    flex-direction: column;
                    flex: 1;
                    overflow: hidden;

                    .file-name {
                        font-weight: 500;
                        margin-bottom: 4px;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                        color: #111827;
                    }

                    .file-size {
                        font-size: 12px;
                        color: #6b7280;
                    }
                }

                .remove-button {
                    background: none;
                    border: none;
                    color: #6b7280;
                    cursor: pointer;
                    padding: 6px 10px;
                    font-size: 16px;
                    border-radius: 6px;
                    transition: all 0.2s ease;
                    margin-left: 8px;

                    &:hover {
                        background-color: #fee2e2;
                        color: #b91c1c;
                    }
                }
            }
        }
    }

    &__footer {
        padding: 20px 24px;
        border-top: 1px solid rgba(224, 224, 224, 0.6);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        background: linear-gradient(to right, #f9fafb, #f3f4f6);

        button {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;

            &.cancel-button {
                background-color: white;
                border: 1px solid #d1d5db;
                color: #4b5563;

                &:hover {
                    background-color: #f3f4f6;
                    border-color: #9ca3af;
                }
            }

            &.upload-button {
                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
                border: none;
                color: white;
                box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2), 0 2px 4px -1px rgba(79, 70, 229, 0.1);

                &:hover {
                    background: linear-gradient(135deg, #4338ca 0%, #6d28d9 100%);
                    transform: translateY(-1px);
                    box-shadow: 0 6px 10px -1px rgba(79, 70, 229, 0.3), 0 2px 4px -1px rgba(79, 70, 229, 0.2);
                }

                &:active {
                    transform: translateY(1px);
                }

                &:disabled {
                    background: linear-gradient(135deg, #c7d2fe 0%, #ddd6fe 100%);
                    cursor: not-allowed;
                    box-shadow: none;
                }
            }
        }
    }
}

:deep(.dropdown-menu) {
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    padding: 4px;
    background: white;
    min-width: 160px;
    
    .dropdown-item {
        padding: 8px 12px;
        border-radius: 6px;
        margin-bottom: 2px;
        transition: all 0.2s ease;
        color: #4b5563;
        font-size: 14px;
        
        &:hover {
            background-color: rgba(79, 70, 229, 0.05);
            color: #4f46e5;
        }
        
        &:active {
            background-color: rgba(79, 70, 229, 0.1);
        }
        
        &:last-child {
            margin-bottom: 0;
        }
    }
}

:deep(.btn-dropdown) {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 14px;
    color: #4b5563;
    transition: all 0.2s ease;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    
    &:hover {
        border-color: #d1d5db;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        color: #111827;
    }
    
    &:focus {
        outline: none;
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
    }
}
</style>
