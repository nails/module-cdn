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
                        <span class="upload-icon">+</span>
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
                    Loading objects...
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
                                        Loading more objects...
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
                                Loading more objects...
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
                        <div class="upload-icon">+</div>
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
    border: 1px solid #cccccc;
    border-radius: 0.25rem;

    @media (min-width: 768px) {
        flex-direction: row;
    }

    .loading {
        position: relative;
        height: 1.5rem;
        background-color: #ececec; // Slightly darker base
        border-radius: 4px;
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
        border-right: 1px solid #e1dfdf;
        border-top-left-radius: 0.25rem;
        border-bottom-left-radius: 0.25rem;
        background: #f8f9fa;

        @media (min-width: 768px) {
            width: 300px;
            flex-shrink: 0;
        }

        &__filter {
            padding: 1rem;
            border-bottom: 1px solid #dee1e6;
            box-sizing: border-box;

            &:last-child {
                margin-bottom: 0;
                border-bottom: none;
            }

            &__title {
                font-size: 1.25rem;
                font-weight: bold;
                margin-top: 0;
                margin-bottom: 0.75rem;
            }

            select,
            input {
                width: 100%;
                margin: 0;
                padding: 8px;
                border: 1px solid #cccccc;
                border-radius: 4px;
                background: #ffffff;
                box-sizing: border-box;
            }

            &--date {
                display: grid;
                grid-template-columns: 1fr auto 1fr;
                gap: 10px;
                align-items: center;

                input {
                    width: 100%;
                    margin: 0;
                    padding: 8px;
                }

                span {
                    text-align: center;
                    font-size: 14px;
                    color: #666;
                }

                .sidebar__filter__title {
                    grid-column: 1 / -1;
                    margin-bottom: 0.75rem;
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
        border-top-right-radius: 0.25rem;
        border-bottom-right-radius: 0.25rem;

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
                background-color: #1a73e8;
                color: white;
                border: none;
                border-radius: 20px;
                padding: 8px 16px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                transition: background-color 0.2s ease;

                &:hover {
                    background-color: #1557b0;
                }

                .upload-icon {
                    font-size: 18px;
                    margin-right: 8px;
                }
            }

            .view-toggle {
                display: inline-flex;
                align-items: center;
                background-color: #f8f9fa;
                padding: 4px;
                border-radius: 20px;
                border: 1px solid #dee1e6;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);

                button {
                    background: none;
                    border: none;
                    padding: 6px 12px;
                    border-radius: 16px;
                    font-size: 14px;
                    color: #5f6368;
                    cursor: pointer;
                    transition: all 0.2s ease;

                    &.active {
                        background-color: white;
                        color: #1a73e8;
                        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
                    }

                    &:hover:not(.active) {
                        background-color: rgba(0, 0, 0, 0.05);
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
                }

                table {
                    width: 100%;
                    border: 0;
                    border-collapse: collapse;
                    background: #ffffff;
                    font-size: 14px;

                    th {
                        padding: 12px;
                        text-align: left;
                        border-bottom: 1px solid #eeeeee;
                        vertical-align: middle;
                        background: #f9f9fa;
                        border-bottom: 1px solid #e7e8ed;
                        text-transform: initial;
                        color: initial;
                        font-weight: 600;
                        font-size: inherit;
                        white-space: nowrap;

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

                    .table-load-more,
                    .table-loading-more {
                        td {
                            text-align: center;
                            padding: 15px;
                            background-color: #f8f9fa;
                            border-top: 1px dashed #dee1e6;
                        }
                    }

                    .table-load-more {
                        td {
                            button {
                                min-width: 120px;
                                height: 40px;
                                background-color: #1a73e8;
                                color: white;
                                border: none;
                                border-radius: 4px;
                                cursor: pointer;
                                font-weight: 500;
                                transition: background-color 0.2s ease;

                                &:hover {
                                    background-color: #1557b0;
                                }
                            }
                        }
                    }

                    .table-loading-more {
                        td {
                            color: #666;
                            font-size: 14px;
                        }
                    }
                }
            }

            &__grid {
                .grid-container {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                    gap: 20px;
                    padding: 0 1rem;
                }

                .grid-load-more,
                .grid-loading-more {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background-color: #f8f9fa;
                    border-radius: 8px;
                    border: 1px dashed #dee1e6;
                    aspect-ratio: 1;
                    padding: 20px;
                    text-align: center;
                }

                .grid-load-more {
                    button {
                        min-width: 120px;
                        height: 40px;
                        background-color: #1a73e8;
                        color: white;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                        font-weight: 500;
                        transition: background-color 0.2s ease;

                        &:hover {
                            background-color: #1557b0;
                        }
                    }
                }

                .grid-loading-more {
                    color: #666;
                    font-size: 14px;
                }

                // Responsive adjustments
                @media (max-width: 768px) {
                    .grid-container {
                        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                        gap: 15px;
                        padding: 0 1rem;
                    }
                }

                @media (max-width: 480px) {
                    .grid-container {
                        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                        gap: 10px;
                        padding: 0 1rem;
                    }
                }
            }

            .empty-state,
            &__loading {
                text-align: center;
                padding: 40px 1rem;
                background: #f5f5f5;
                border-radius: 8px;
                color: #666666;
                margin: 0 1rem;

                p {
                    margin: 0;
                    font-size: 16px;
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

    &__overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }

    &__container {
        position: relative;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    &__header {
        padding: 16px 20px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;

        h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 500;
            color: #333;
        }

        .close-button {
            background: none;
            border: none;
            font-size: 20px;
            color: #666;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            transition: background-color 0.2s;

            &:hover {
                background-color: #f5f5f5;
            }
        }
    }

    &__body {
        padding: 20px;
        overflow-y: auto;
        flex: 1;

        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
            transition: border-color 0.2s, background-color 0.2s;
            cursor: pointer;

            &:hover, &.drag-over {
                border-color: #1a73e8;
                background-color: rgba(26, 115, 232, 0.05);
            }

            .upload-icon {
                font-size: 40px;
                color: #666;
                margin-bottom: 10px;
            }

            h4 {
                margin: 0 0 10px;
                font-weight: 500;
                color: #333;
            }

            p {
                margin: 0;
                color: #666;
                font-size: 14px;
            }

            .file-input {
                display: none;
            }
        }

        .bucket-selector {
            margin-bottom: 20px;

            label {
                display: block;
                margin-bottom: 8px;
                font-weight: 500;
                color: #333;
            }

            select {
                width: 100%;
                padding: 10px;
                border: 1px solid #ccc;
                border-radius: 4px;
                font-size: 14px;
                background-color: white;
            }
        }

        .error-message {
            margin-top: 15px;
            padding: 10px;
            background-color: #ffebee;
            color: #c62828;
            border-radius: 4px;
            border-left: 4px solid #c62828;
            font-size: 14px;
        }
        
        .success-message {
            margin-top: 15px;
            padding: 10px;
            background-color: #e8f5e9;
            color: #2e7d32;
            border-radius: 4px;
            border-left: 4px solid #2e7d32;
            font-size: 14px;
        }

        .file-list {
            margin-top: 20px;

            h4 {
                margin: 0 0 10px;
                font-weight: 500;
                color: #333;
            }

            .file-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                margin-bottom: 8px;
                background-color: #f8f9fa;

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
                    }

                    .file-size {
                        font-size: 12px;
                        color: #666;
                    }
                }

                .remove-button {
                    background: none;
                    border: none;
                    color: #666;
                    cursor: pointer;
                    padding: 4px 8px;
                    font-size: 14px;
                    border-radius: 4px;
                    transition: background-color 0.2s;

                    &:hover {
                        background-color: #e0e0e0;
                    }
                }
            }
        }
    }

    &__footer {
        padding: 16px 20px;
        border-top: 1px solid #e0e0e0;
        display: flex;
        justify-content: flex-end;
        gap: 10px;

        button {
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;

            &.cancel-button {
                background-color: white;
                border: 1px solid #ccc;
                color: #333;

                &:hover {
                    background-color: #f5f5f5;
                }
            }

            &.upload-button {
                background-color: #1a73e8;
                border: none;
                color: white;

                &:hover {
                    background-color: #1557b0;
                }

                &:disabled {
                    background-color: #a8c7fa;
                    cursor: not-allowed;
                }
            }
        }
    }
}
</style>
