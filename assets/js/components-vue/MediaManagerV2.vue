<template>
    <div class="nails-module-cdn-media-manager-v2" :class="{ 'is-modal': isModal }">
        <div class="sidebar">
            <div class="sidebar__filter sidebar__filter--keyword">
                <p class="sidebar__filter__title">Search &amp; Filter</p>
                <div class="keyword-input-wrapper">
                    <input type="text" v-model="keywords" placeholder="Keywords" />
                    <button
                        v-if="keywords"
                        class="clear-keyword"
                        @click="keywords = null"
                        title="Clear search"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="sidebar__filter sidebar__filter--bucket">
                <p class="sidebar__filter__title">Bucket</p>
                <div class="loading" v-if="loadingBuckets"></div>
                <div class="bucket-filter-container">
                    <bucket-selector
                        v-if="!loadingBuckets && buckets.length > 0"
                        v-model="selectedBuckets"
                        :buckets="buckets"
                        placeholder="Select buckets"
                        :single-select="false"
                        :show-actions="true"
                        :offset-dropdown-arrow="userCanCreateBucket && !loadingBuckets"
                        @delete-bucket="handleDeleteBucket"
                        class="bucket-filter"
                    />
                    <button
                        v-if="userCanCreateBucket && !loadingBuckets"
                        class="create-bucket-button"
                        @click="openCreateBucketModal"
                        title="Create New Bucket"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
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
                <div class="date-range-picker">
                    <div class="date-range-input" :class="{ 'has-value': dateLower || dateUpper }">
                        <div class="date-input-wrapper" :class="{ 'has-value': !!dateLower }">
                            <input
                                type="date"
                                v-model="dateLower"
                                class="date-input"
                                :max="dateUpper"
                            />
                            <span class="date-placeholder">Start date</span>
                        </div>
                        <span class="date-separator">to</span>
                        <div class="date-input-wrapper" :class="{ 'has-value': !!dateUpper }">
                            <input
                                type="date"
                                v-model="dateUpper"
                                class="date-input"
                                :min="dateLower"
                            />
                            <span class="date-placeholder">End date</span>
                        </div>
                        <button
                            class="clear-date"
                            @click="clearDateRange"
                            v-if="dateLower || dateUpper"
                            title="Clear date range"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="calendar-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="sidebar__filter sidebar__filter--reset" v-if="hasActiveFilters">
                <button class="reset-filters-button" @click="resetAllFilters">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="reset-icon">
                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                    </svg>
                    Reset Filters
                </button>
            </div>
            <div class="sidebar__filter sidebar__filter--trash">
                <button class="trash-button" @click="openTrashModal">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="trash-icon">
                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    View Trash
                </button>
            </div>
            <div v-if="switchBackUrl" class="sidebar__switch-back">
                <a :href="switchBackUrl" class="switch-back-btn" tabindex="0">
                    <span>Switch back to the original Media Manager</span>
                    <span class="beta-badge">BETA</span>
                </a>
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
                    <div v-if="selectedObject" class="selected-object-info">
                        <span class="selected-object-info__label">Currently Selected:</span>
                        <span class="selected-object-info__name">{{ selectedObject.object.name }}</span>
                        <span class="selected-object-info__details">
                            ({{ selectedObject.object.mime.split('/')[1].toUpperCase() }}, {{ selectedObject.object.size.human }})
                        </span>
                    </div>
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
                                    :has-callback="callbackFunction.length > 0"
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
                                :has-callback="callbackFunction.length > 0"
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
        <ModalUpload
            :visible="showUploadModal"
            :buckets="buckets"
            :max-upload-size="maxUploadSize"
            @close="showUploadModal = false"
            @upload-success="handleUploadSuccess"
        />

        <!-- Edit Modal -->
        <ModalEdit
            :visible="showEditModal"
            :editingObject="editingObject"
            :isEditing="isEditing"
            :editError="editError"
            :editSuccess="editSuccess"
            @close="closeEditModal"
            @save="saveObjectEditFromModal"
        />

        <!-- URL Copy Modal -->
        <ModalUrlCopy
            :visible="showUrlCopyModal"
            :urlToCopy="urlToCopy"
            :urlCopyError="urlCopyError"
            :urlCopySuccess="urlCopySuccess"
            @close="closeUrlCopyModal"
            @copy-success="handleUrlCopySuccess"
            @copy-error="handleUrlCopyError"
        />

        <!-- Delete Confirmation Modal -->
        <ModalDelete
            :visible="showDeleteModal"
            :deletingObject="deletingObject"
            :isDeleting="isDeleting"
            :deleteError="deleteError"
            :deleteSuccess="deleteSuccess"
            :siteUrl="siteUrl"
            @close="closeDeleteModal"
            @confirm="confirmDelete"
        />

        <ModalReplaceFile
            :visible="showReplaceModal"
            :object="replacingObject"
            :site-url="siteUrl"
            :max-upload-size="maxUploadSize"
            @close="showReplaceModal = false"
            @file-replaced="handleFileReplaced"
        />

        <ModalMoveCopy
            v-if="showMoveCopyModal"
            :visible="true"
            :object="moveCopyObject"
            :buckets="buckets"
            :site-url="siteUrl"
            @close="closeMoveCopyModal"
            @success="handleMoveCopySuccess"
        />

        <!-- Create Bucket Modal -->
        <ModalCreateBucket
            :visible="showCreateBucketModal"
            :newBucketName="newBucketName"
            :isCreatingBucket="isCreatingBucket"
            :createBucketError="createBucketError"
            :createBucketSuccess="createBucketSuccess"
            @close="closeCreateBucketModal"
            @create="handleCreateBucketFromModal"
        />

        <!-- Delete Bucket Confirmation Modal -->
        <ModalDeleteBucket
            :visible="showDeleteBucketModal"
            :bucketToDelete="bucketToDelete"
            :isDeletingBucket="isDeletingBucket"
            :deleteBucketError="deleteBucketError"
            :deleteBucketSuccess="deleteBucketSuccess"
            @close="closeDeleteBucketModal"
            @confirm="confirmDeleteBucket"
        />

        <!-- Trash Modal -->
        <ModalTrash
            :visible="showTrashModal"
            :trashedItems="trashedItems"
            :selectedTrashedItems="selectedTrashedItems"
            :loadingTrashedItems="loadingTrashedItems"
            :isRestoring="isRestoring"
            :restoreError="restoreError"
            :restoreSuccess="restoreSuccess"
            @close="closeTrashModal"
            @restore="restoreTrashedItems"
            @toggle-selection="toggleTrashedItem"
            @toggle-select-all="toggleSelectAllTrashedItems"
        />

        <ModalMoveCopy
            v-if="showMoveCopyModal"
            :visible="true"
            :object="moveCopyObject"
            :buckets="buckets"
            :site-url="siteUrl"
            @close="closeMoveCopyModal"
            @success="handleMoveCopySuccess"
        />

        <!-- Create Bucket Modal -->
        <ModalCreateBucket
            :visible="showCreateBucketModal"
            :newBucketName="newBucketName"
            :isCreatingBucket="isCreatingBucket"
            :createBucketError="createBucketError"
            :createBucketSuccess="createBucketSuccess"
            @close="closeCreateBucketModal"
            @create="handleCreateBucketFromModal"
        />

        <!-- Delete Bucket Confirmation Modal -->
        <ModalDeleteBucket
            :visible="showDeleteBucketModal"
            :bucketToDelete="bucketToDelete"
            :isDeletingBucket="isDeletingBucket"
            :deleteBucketError="deleteBucketError"
            :deleteBucketSuccess="deleteBucketSuccess"
            @close="closeDeleteBucketModal"
            @confirm="confirmDeleteBucket"
        />
    </div>
</template>

<script>
import axios from 'axios';
import { debounce } from 'lodash'
import MultiSelect from './MediaManagerV2/MultiSelect.vue'
import BucketSelector from './MediaManagerV2/BucketSelector.vue'
import ObjectListItem from './MediaManagerV2/ObjectListItem.vue'
import ObjectGridItem from './MediaManagerV2/ObjectGridItem.vue'
import ModalReplaceFile from './MediaManagerV2/Modals/ModalReplaceFile.vue'
import ModalMoveCopy from './MediaManagerV2/Modals/ModalMoveCopy.vue'
import ModalBase from './MediaManagerV2/Modals/ModalBase.vue'
import ModalUpload from './MediaManagerV2/Modals/ModalUpload.vue'
import ModalEdit from './MediaManagerV2/Modals/ModalEdit.vue'
import ModalUrlCopy from './MediaManagerV2/Modals/ModalUrlCopy.vue'
import ModalDelete from './MediaManagerV2/Modals/ModalDelete.vue'
import ModalCreateBucket from './MediaManagerV2/Modals/ModalCreateBucket.vue'
import ModalDeleteBucket from './MediaManagerV2/Modals/ModalDeleteBucket.vue'
import ModalTrash from './MediaManagerV2/Modals/ModalTrash.vue'
import Button from './MediaManagerV2/Button.vue'
import FilePreview from './MediaManagerV2/FilePreview.vue'

export default {
    name: 'MediaManagerV2',
    components: {
        MultiSelect,
        BucketSelector,
        ObjectListItem,
        ObjectGridItem,
        ModalReplaceFile,
        ModalMoveCopy,
        Button,
        ModalBase,
        FilePreview,
        ModalUpload,
        ModalEdit,
        ModalUrlCopy,
        ModalDelete,
        ModalCreateBucket,
        ModalDeleteBucket,
        ModalTrash
    },
    props: {
        maxUploadSize: {
            type: Number,
            default: 10485760 // 10MB in bytes
        },
        userCanCreateBucket: {
            type: Boolean,
            default: false
        },
        switchBackUrl: {
            type: String,
            default: ''
        }
    },
    data() {
        return {
            keywords: null,
            objects: [],
            loadingObjects: true,
            loadingMoreObjects: false,
            buckets: [],
            selectedBuckets: [],
            selectedUploadBucket: [],  // Changed to array for MultiSelect compatibility
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
            dragOver: false,
            isUploading: false,
            uploadError: null,
            uploadSuccess: false,
            uploadProgress: {},
            overallProgress: 0,
            showEditModal: false,
            editingObject: null,
            editError: null,
            editSuccess: false,
            isEditing: false,
            isModal: false,
            callbackFunction: [],
            callbackHandler: null,
            showUrlCopyModal: false,
            urlToCopy: null,
            urlCopyError: null,
            urlCopySuccess: false,
            showDeleteModal: false,
            deletingObject: null,
            deleteError: null,
            deleteSuccess: false,
            isDeleting: false,
            siteUrl: window.SITE_URL || '',
            selectedObject: null,
            showReplaceModal: false,
            replacingObject: null,
            showCreateBucketModal: false,
            newBucketName: '',
            isCreatingBucket: false,
            createBucketError: null,
            createBucketSuccess: false,
            showDeleteBucketModal: false,
            bucketToDelete: null,
            isDeletingBucket: false,
            deleteBucketError: null,
            deleteBucketSuccess: false,
            // Trash modal properties
            showTrashModal: false,
            trashedItems: [],
            selectedTrashedItems: [],
            loadingTrashedItems: false,
            isRestoring: false,
            restoreError: null,
            restoreSuccess: false,
            // Move/Copy modal properties
            showMoveCopyModal: false,
            moveCopyObject: null
        }
    },

    created() {
        this.parseUrlParams();
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

    computed: {
        hasActiveFilters() {
            return !!(
                this.keywords ||
                this.selectedBuckets.length > 0 ||
                this.selectedFileTypes.length > 0 ||
                this.selectedUploaders.length > 0 ||
                this.dateLower ||
                this.dateUpper
            );
        }
    },

    methods: {

        parseUrlParams() {
            const urlParams = new URLSearchParams(window.location.search);

            //  Modal
            this.isModal = !!parseInt(urlParams.get('isModal'));

            //  Default Bucket
            const bucketId = urlParams.get('bucket_id');
            if (bucketId) {
                this.selectedBuckets = [parseInt(bucketId)];
            }

            //  Selected Object
            const objectId = urlParams.get('object_id');
            if (objectId) {
                this.fetchSelectedObject(objectId);
            }

            //  Callback
            if (urlParams.has('CKEditorFuncNum')) {
                this.callbackHandler = 'ckeditor';
                this.callbackFunction = [
                    urlParams.get('CKEditorFuncNum')
                ];

            } else {
                this.callbackHandler = 'picker';
                const callbackParams = [];
                urlParams.forEach((value, key) => {
                    if (key.startsWith('callback[')) {
                        callbackParams.push(value);
                    }
                });

                if (callbackParams.length > 0) {
                    this.callbackFunction = callbackParams;
                }
            }
        },

        async fetchAllBuckets(url = `${this.siteUrl}api/cdn/bucket`) {
            try {
                this.loadingBuckets = true;
                let buckets = await this.iterateOverApiPages(url);

                this.buckets = buckets.map(bucket => {
                    // Create a new object to avoid modifying the original
                    const formattedBucket = {...bucket};

                    // Format the object_count property
                    if (formattedBucket.object_count !== undefined) {
                        formattedBucket.object_count_human = `${formattedBucket.object_count} ${formattedBucket.object_count === 1 ? 'object' : 'objects'}`;
                    }

                    return formattedBucket;
                });
            } catch (error) {
                this.error('Failed to load buckets: ' + error.message);
            } finally {
                this.loadingBuckets = false;
            }
        },

        async fetchAllFileTypes(url = `${this.siteUrl}api/cdn/mediamanagerv2/filetypes`) {
            try {
                this.loadingFileTypes = true;
                this.fileTypes = await this.iterateOverApiPages(url);
            } catch (error) {
                this.error('Failed to load file types: ' + error.message);
            } finally {
                this.loadingFileTypes = false;
            }

        },

        async fetchAllUploaders(url = `${this.siteUrl}api/cdn/mediamanagerv2/uploaders`) {
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
                const response = await axios.get(`${this.siteUrl}api/cdn/mediamanagerv2/objects`, {
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

                const response = await axios.get(`${this.siteUrl}api/cdn/mediamanagerv2/objects`, {
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

        handleObjectAction({action, item}) {
            switch (action) {
                case 'insert':
                    this.invokeCallbackFunction(item);
                    break;
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
                case 'replace':
                    this.replacingObject = item;
                    this.showReplaceModal = true;
                    break;
                case 'move-copy':
                    this.moveCopyObject = item;
                    this.showMoveCopyModal = true;
                    break;
            }
        },

        editItem(item) {
            this.editingObject = {
                id: item.id,
                filename_display: item.filename_display || item.file.name.human,
                metadata: item.metadata || []
            };
            this.showEditModal = true;
            this.editError = null;
            this.editSuccess = false;
        },

        deleteItem(item) {
            this.deletingObject = item;
            this.showDeleteModal = true;
            this.deleteError = null;
            this.deleteSuccess = false;
        },

        downloadItem(item) {
            // Create a temporary anchor element
            const link = document.createElement('a');
            link.href = item.url.download;
            link.download = item.file.name.human; // Set the download filename
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },

        copyUrl(item) {
            this.urlToCopy = {
                src: item.url.src,
                download: item.url.download,
                humanName: item.file.name.human
            };
            this.showUrlCopyModal = true;
            this.urlCopyError = null;
            this.urlCopySuccess = false;
        },

        handleUrlCopySuccess() {
            this.urlCopySuccess = true;
            this.urlCopyError = null;
            setTimeout(() => {
                this.urlCopySuccess = false;
            }, 2000);
        },

        handleUrlCopyError(message) {
            this.urlCopyError = message;
            this.urlCopySuccess = false;
        },

        closeUrlCopyModal() {
            this.showUrlCopyModal = false;
            this.urlToCopy = null;
            this.urlCopyError = null;
            this.urlCopySuccess = false;
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

        closeDeleteBucketModal() {
            this.showDeleteBucketModal = false;
            this.bucketToDelete = null;
            this.isDeletingBucket = false;
            this.deleteBucketError = null;
            this.deleteBucketSuccess = false;
        },

        async confirmDeleteBucket() {
            if (!this.bucketToDelete) return;

            this.isDeletingBucket = true;
            this.deleteBucketError = null;
            this.deleteBucketSuccess = false;

            try {
                // Make DELETE request to the API
                await axios.delete(`${this.siteUrl}api/cdn/bucket/${this.bucketToDelete.id}`);

                // Show success message
                this.deleteBucketSuccess = true;
                console.log(`Bucket "${this.bucketToDelete.label}" deleted successfully`);

                // Refresh the bucket list
                await this.fetchAllBuckets();

                // If the deleted bucket was selected, reset the selection
                if (this.selectedBuckets.includes(this.bucketToDelete.id)) {
                    this.selectedBuckets = this.selectedBuckets.filter(id => id !== this.bucketToDelete.id);
                }

                // Close modal after a delay
                setTimeout(() => {
                    this.closeDeleteBucketModal();
                }, 1500);
            } catch (error) {
                console.error('Failed to delete bucket:', error);
                this.deleteBucketError = error.response?.data?.error || error.message;
                this.isDeletingBucket = false;
            }
        },

        openUploadModal() {
            this.showUploadModal = true;
            this.filesToUpload = [];
            // If exactly one bucket is selected in the filter, use that as the default
            this.selectedUploadBucket = this.selectedBuckets.length === 1 ? [this.selectedBuckets[0]] : [];
            this.uploadError = null;
            this.uploadSuccess = false;
        },

        closeEditModal() {
            this.showEditModal = false;
            this.editingObject = null;
            this.editError = null;
            this.editSuccess = false;
        },

        async saveObjectEdit() {
            this.isEditing = true;
            this.editError = null;

            const formData = new FormData();
            formData.append('object_id', this.editingObject.id);
            formData.append('filename_display', this.editingObject.filename_display);

            // Add metadata to form data
            if (this.editingObject.metadata) {
                this.editingObject.metadata.forEach((item, index) => {
                    formData.append(`metadata[${index}][key]`, item.key);
                    formData.append(`metadata[${index}][value]`, item.value);
                });
            }

            try {
                const response = await axios.post(
                    `${this.siteUrl}api/cdn/object/edit`,
                    formData,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }
                );

                // Update the local object instance with the new data
                const index = this.objects.findIndex(obj => obj.id === this.editingObject.id);
                if (index !== -1) {
                    this.objects[index].file.name.human = response.data.data.object.object.name;
                    this.objects[index].metadata = response.data.data.object.metadata;
                }

                this.editSuccess = true;
                setTimeout(() => this.closeEditModal(), 1500);
            } catch (error) {
                console.log(error);
                this.editError = error.response?.data?.error || 'Failed to update object';
            } finally {
                this.isEditing = false;
            }
        },

        invokeCallbackFunction(selectedObject) {
            if (this.callbackFunction.length) {
                if (this.callbackHandler === 'picker') {
                    this.callbackPicker(selectedObject);
                } else if (this.callbackHandler === 'ckeditor') {
                    this.callbackCkeditor(selectedObject);
                }
            }

        },

        callbackCkeditor(selectedObject) {
            window.opener.CKEDITOR.tools.callFunction(this.callbackFunction[0], selectedObject.url.src);
            window.close();
        },

        callbackPicker(selectedObject) {
            if (this.isModal) {

                let namespace = 'parent.' + this.callbackFunction[0];
                let method = this.callbackFunction[1];

                this
                    .getFunctionFromString(namespace + '.' + method)
                    .call(
                        this.getFunctionFromString(namespace),
                        selectedObject.id
                    );

                window.parent.$.fancybox.close();

            } else {
                window
                    .opener[this.callbackFunction[0]][this.callbackFunction[1]]
                    .call(null, selectedObject.id);
            }
        },

        getFunctionFromString(string) {
            let scope = window;
            let scopeSplit = string.split('.');

            for (let i = 0; i < scopeSplit.length - 1; i++) {

                if (scopeSplit[i].indexOf('[') !== -1) {

                    var arrayItem = scopeSplit[i].substr(0, scopeSplit[i].length - 1).split('[');
                    scope = scope[arrayItem[0]];
                    if (scope === undefined) {
                        return;
                    }

                    scope = scope[arrayItem[1].replace(/^['"](.*)['"]$/, '$1')];

                } else {
                    scope = scope[scopeSplit[i]];
                }

                if (scope === undefined) {
                    return;
                }
            }

            return scope[scopeSplit[scopeSplit.length - 1]];
        },

        async confirmDelete() {
            this.isDeleting = true;
            this.deleteError = null;
            this.deleteSuccess = false;

            try {
                const formData = new FormData();
                formData.append('object_id', this.deletingObject.id);

                await axios.post(
                    `${this.siteUrl}api/cdn/object/delete`,
                    formData,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }
                );

                // Remove the object from the UI
                const index = this.objects.findIndex(obj => obj.id === this.deletingObject.id);
                if (index !== -1) {
                    this.objects.splice(index, 1);
                }

                this.deleteSuccess = true;
                // Keep isDeleting true to keep the button disabled
                setTimeout(() => {
                    this.closeDeleteModal();
                }, 1500);
            } catch (error) {
                console.error('Delete failed:', error);
                this.deleteError = error.response?.data?.error || 'Failed to delete file';
                this.isDeleting = false; // Only reset isDeleting on error
            }
        },

        closeDeleteModal() {
            this.showDeleteModal = false;
            this.deletingObject = null;
            this.deleteError = null;
            this.deleteSuccess = false;
            this.isDeleting = false;
        },

        clearDateRange() {
            this.dateLower = null;
            this.dateUpper = null;
        },

        resetAllFilters() {
            this.keywords = null;
            this.selectedBuckets = [];
            this.selectedFileTypes = [];
            this.selectedUploaders = [];
            this.dateLower = null;
            this.dateUpper = null;
            this.page = 1;
            this.doSearch();
        },

        async fetchSelectedObject(objectId) {
            try {
                const response = await fetch(`${this.siteUrl}/api/cdn/object?id=${objectId}`);
                const data = await response.json();

                if (data.status === 200) {
                    this.selectedObject = data.data;
                }
            } catch (error) {
                // Silently fail as requested
                console.error('Failed to fetch selected object:', error);
            }
        },

        handleFileReplaced(updatedObject) {
            // Update the local object instance with the new data
            const index = this.objects.findIndex(obj => obj.id === updatedObject.id);
            if (index !== -1) {
                this.objects[index] = updatedObject;
            }
            this.showReplaceModal = false;
            this.replacingObject = null;
        },

        handleMoveCopySuccess({action, object}) {
            if (action === 'move') {
                // For move: remove the original object from the list
                const index = this.objects.findIndex(obj => obj.id === this.moveCopyObject.id);
                if (index !== -1) {
                    this.objects.splice(index, 1);
                }
            } else if (action === 'copy') {
                // For copy: add the new object to the list if it matches current filters
                // Only add if the new object's bucket is in the selected buckets (or no buckets are selected)
                if (this.selectedBuckets.length === 0 || this.selectedBuckets.includes(object.bucket.id)) {
                    this.objects.unshift(object);
                }
            }

            this.closeMoveCopyModal();
        },

        closeMoveCopyModal() {
            this.showMoveCopyModal = false;
            this.moveCopyObject = null;
        },

        openCreateBucketModal() {
            this.showCreateBucketModal = true;
            this.newBucketName = '';
            this.createBucketError = null;
            this.createBucketSuccess = false;
        },

        closeCreateBucketModal() {
            this.showCreateBucketModal = false;
            this.newBucketName = '';
            this.createBucketError = null;
            this.createBucketSuccess = false;
        },

        async createBucket() {
            if (!this.newBucketName || this.isCreatingBucket) {
                return;
            }

            this.isCreatingBucket = true;
            this.createBucketError = null;
            this.createBucketSuccess = false;

            try {
                const response = await axios.post(
                    `${this.siteUrl}api/cdn/bucket`,
                    {
                        label: this.newBucketName
                    }
                );

                // Show success message
                this.createBucketSuccess = true;

                // Refresh the bucket list
                await this.fetchAllBuckets();

                // Close the modal after a delay
                setTimeout(() => {
                    this.closeCreateBucketModal();
                }, 1500);
            } catch (error) {
                console.error('Create bucket failed:', error);
                if (error.response && error.response.data && error.response.data.error) {
                    this.createBucketError = error.response.data.error;
                } else {
                    this.createBucketError = 'An error occurred while creating the bucket. Please try again.';
                }
            } finally {
                this.isCreatingBucket = false;
            }
        },

        formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
        },

        openTrashModal() {
            this.showTrashModal = true;
            this.trashedItems = [];
            this.selectedTrashedItems = [];
            this.restoreError = null;
            this.restoreSuccess = false;
            this.fetchTrashedItems();
        },

        closeTrashModal() {
            this.showTrashModal = false;
            this.trashedItems = [];
            this.selectedTrashedItems = [];
            this.restoreError = null;
            this.restoreSuccess = false;
            this.isRestoring = false;
        },

        async fetchTrashedItems(url = null, allItems = []) {
            this.loadingTrashedItems = true;
            try {
                const response = await axios.get(url || `${this.siteUrl}api/cdn/mediamanagerv2/trash`);
                const items = [...allItems, ...response.data.data];

                // Check if there's a next page
                if (response.data.meta?.pagination?.next) {
                    // Recursively fetch the next page
                    return this.fetchTrashedItems(response.data.meta.pagination.next, items);
                } else {
                    // No more pages, update the trashedItems with all fetched items
                    this.trashedItems = items;
                    this.loadingTrashedItems = false;
                }
            } catch (error) {
                console.error('Failed to fetch trashed items:', error);
                this.restoreError = 'Failed to load trashed items. Please try again.';
                this.loadingTrashedItems = false;
            }
        },

        toggleTrashedItem(itemId) {
            const index = this.selectedTrashedItems.indexOf(itemId);
            if (index === -1) {
                // Add to selection
                this.selectedTrashedItems.push(itemId);
            } else {
                // Remove from selection
                this.selectedTrashedItems.splice(index, 1);
            }
        },

        async restoreTrashedItems() {
            if (this.selectedTrashedItems.length === 0) {
                this.restoreError = 'Please select at least one item to restore.';
                return;
            }

            this.isRestoring = true;
            this.restoreError = null;
            this.restoreSuccess = false;

            try {
                const response = await axios.post(
                    `${this.siteUrl}api/cdn/mediamanagerv2/restore`,
                    {
                        object_ids: this.selectedTrashedItems
                    }
                );

                const {success, error} = response.data.data;

                // Remove successfully restored items from the list
                if (success && success.length > 0) {
                    this.trashedItems = this.trashedItems.filter(
                        item => !success.includes(item.id)
                    );

                    // Clear these items from selection
                    this.selectedTrashedItems = this.selectedTrashedItems.filter(
                        id => !success.includes(id)
                    );

                    // Reload the main list of objects to show the restored items
                    this.doSearch();
                }

                // Handle errors if any
                if (error && error.length > 0) {
                    const successCount = success ? success.length : 0;
                    this.restoreError = `${successCount} item(s) were successfully restored, but ${error.length} item(s) could not be restored.`;
                } else {
                    // No errors, show success message
                    this.restoreSuccess = true;

                    // Close modal automatically after a delay if no errors
                    setTimeout(() => {
                        this.closeTrashModal();
                    }, 1500);
                }
            } catch (error) {
                console.error('Failed to restore items:', error);
                this.restoreError = error.response?.data?.error || 'Failed to restore items. Please try again.';
            } finally {
                this.isRestoring = false;
            }
        },

        handleUploadSuccess() {
            this.uploadSuccess = true;
            this.uploadError = null;
            this.uploadProgress = {};
            this.overallProgress = 0;
            this.filesToUpload = [];
            this.showUploadModal = false;
            this.keywords = null;
            this.selectedBuckets = [];
            this.selectedFileTypes = [];
            this.selectedUploaders = [];
            this.dateLower = null;
            this.dateUpper = null;
            this.page = 1;
            this.doSearch();
        },

        saveObjectEditFromModal(editedObject) {
            this.editingObject = editedObject;
            this.saveObjectEdit();
        },

        handleCreateBucketFromModal(bucketName) {
            this.newBucketName = bucketName;
            this.createBucket();
        },

        toggleSelectAllTrashedItems() {
            if (this.selectedTrashedItems.length === this.trashedItems.length) {
                this.selectedTrashedItems = [];
            } else {
                this.selectedTrashedItems = this.trashedItems.map(item => item.id);
            }
        },

        handleDeleteBucket(bucket) {
            this.bucketToDelete = bucket;
            this.showDeleteBucketModal = true;
            this.deleteBucketError = null;
            this.deleteBucketSuccess = false;
        }
    }
}
</script>

<style lang="scss" scoped>
.nails-module-cdn-media-manager-v2 {
    display: flex;
    flex-direction: column;
    gap: 0;
    height: 100vh; /* Full viewport height */
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
        position: relative;
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 0;
        justify-content: space-between;
        width: 100%;
        border-right: 1px solid #e5e7eb;
        border-top-left-radius: 0.5rem;
        border-bottom-left-radius: 0.5rem;
        background: linear-gradient(to bottom, #f9fafb, #f3f4f6);
        box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.5);
        display: flex;
        flex-direction: column;
        position: relative;
        overflow-y: auto;

        @media (min-width: 768px) {
            width: 350px;
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

            &--trash {
                position: sticky;
                bottom: 0;
                left: 0;
                width: 100%;
                background-color: #f9fafb;
                border-top: 1px solid #e5e7eb;
                z-index: 10;
                margin-top: auto; /* Push to the bottom of the flex container */
            }

            .bucket-filter-container {
                position: relative;
                width: 100%;

                .bucket-filter {
                    width: 100%;

                    :deep(.multi-select__selected) {
                        padding-right: 2.5rem; /* Make room for the create button */
                    }

                    :deep(.multi-select__dropdown) {
                        width: 100%; /* Ensure dropdown is full width */
                    }
                }

                .create-bucket-button {
                    position: absolute;
                    right: 0.4rem;
                    top: 0.4rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 1.75rem;
                    height: 1.75rem;
                    border: 1px solid #e5e7eb;
                    border-radius: 0.375rem;
                    background-color: #f9fafb;
                    color: #4f46e5;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    z-index: 10;

                    &:hover {
                        background-color: #4f46e5;
                        color: white;
                        border-color: #4f46e5;
                    }

                    svg {
                        width: 1rem;
                        height: 1rem;
                    }
                }
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
                .date-range-picker {
                    width: 100%;
                }

                .date-range-input {
                    position: relative;
                    width: 100%;
                    display: flex;
                    align-items: center;
                    background: white;
                    border: 1px solid #d1d5db;
                    border-radius: 8px;
                    padding: 0 12px;
                    transition: all 0.2s ease;

                    &:hover {
                        border-color: #9ca3af;
                    }

                    &:focus-within {
                        border-color: #4f46e5;
                        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
                    }

                    &.has-value {
                        border-color: #4f46e5;
                        background: linear-gradient(to right, rgba(79, 70, 229, 0.05), transparent);
                    }

                    .date-input-wrapper {
                        position: relative;
                        flex: 1;

                        &.has-value {
                            .date-input {
                                opacity: 1;
                            }

                            .date-placeholder {
                                display: none;
                            }
                        }
                    }

                    .date-input {
                        width: 100%;
                        border: none;
                        padding: 10px 4px;
                        font-size: 14px;
                        color: #374151;
                        background: transparent;
                        outline: none;
                        box-shadow: none;
                        position: relative;
                        z-index: 2;
                        opacity: 0;

                        &::-webkit-calendar-picker-indicator {
                            opacity: 0;
                            cursor: pointer;
                            position: absolute;
                            right: 0;
                            top: 0;
                            width: 100%;
                            height: 100%;
                            z-index: 3;
                        }
                    }

                    .date-placeholder {
                        position: absolute;
                        left: 4px;
                        top: 50%;
                        transform: translateY(-50%);
                        color: #9ca3af;
                        font-size: 14px;
                        pointer-events: none;
                        z-index: 1;
                    }

                    .date-separator {
                        color: #6b7280;
                        font-size: 14px;
                        padding: 0 4px;
                    }

                    .calendar-icon {
                        width: 16px;
                        height: 16px;
                        color: #6b7280;
                        flex-shrink: 0;
                    }

                    .clear-date {
                        background: none;
                        border: none;
                        padding: 4px;
                        color: #6b7280;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        border-radius: 4px;
                        transition: all 0.2s ease;

                        svg {
                            width: 14px;
                            height: 14px;
                        }

                        &:hover {
                            background-color: #f3f4f6;
                            color: #4b5563;
                        }
                    }
                }
            }

            &--keyword {
                .keyword-input-wrapper {
                    position: relative;
                    width: 100%;

                    input {
                        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'%3E%3C/path%3E%3C/svg%3E");
                        background-repeat: no-repeat;
                        background-position: 10px center;
                        background-size: 20px;
                        padding-left: 40px;
                        padding-right: 40px; // Make room for the clear button
                    }

                    .clear-keyword {
                        position: absolute;
                        right: 8px;
                        top: 50%;
                        transform: translateY(-50%);
                        background: none;
                        border: none;
                        padding: 4px;
                        color: #6b7280;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        border-radius: 4px;
                        transition: all 0.2s ease;

                        svg {
                            width: 14px;
                            height: 14px;
                        }

                        &:hover {
                            background-color: #f3f4f6;
                            color: #4b5563;
                        }
                    }
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

            &--reset {
                padding: 1.25rem;
                border-top: 1px solid #e5e7eb;
                background: linear-gradient(to bottom, rgba(249, 250, 251, 0.8), rgba(243, 244, 246, 0.8));

                .reset-filters-button {
                    width: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem;
                    padding: 0.75rem;
                    background: white;
                    border: 1px dashed #d1d5db;
                    border-radius: 8px;
                    color: #6b7280;
                    font-size: 0.875rem;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);

                    .reset-icon {
                        width: 1rem;
                        height: 1rem;
                    }

                    &:hover {
                        background: #f9fafb;
                        border-style: solid;
                        border-color: #9ca3af;
                        color: #4b5563;
                    }

                    &:active {
                        background: #f3f4f6;
                        transform: translateY(1px);
                    }
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
        overflow-y: auto; /* Make the body section scrollable */
        display: flex;
        flex-direction: column;
        max-height: 100vh; /* Ensure it doesn't exceed viewport height */

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
            flex-grow: 1;
            overflow-y: auto;

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

.reset-filters-button {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background-color: white;
    color: #4b5563;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;

    svg {
        width: 16px;
        height: 16px;
        color: #6b7280;
    }

    &:hover {
        background-color: #f3f4f6;
        border-color: #9ca3af;
    }
}

.trash-button {
    display: flex;
    align-items: center;
    justify-content: center; /* Center align the text */
    gap: 8px;
    padding: 8px 16px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background-color: white;
    color: #4b5563;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 100%;

    svg {
        width: 16px;
        height: 16px;
        color: #6b7280;
    }

    &:hover {
        background-color: #f3f4f6;
        border-color: #9ca3af;
    }
}

.upload-button {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
    background: #4f46e5;
    border: 1px solid transparent;
    color: white;

    &:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    svg {
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
    }

    &:hover:not(:disabled) {
        background: #4338ca;
    }

    &:active:not(:disabled) {
        background: #3730a3;
    }
}

.hidden {
    display: none;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    text-align: center;
    color: #6b7280;

    .empty-icon {
        width: 3rem;
        height: 3rem;
        color: #9ca3af;
        margin-bottom: 1rem;
    }

    p {
        font-size: 0.875rem;
    }
}

.sidebar__switch-back {
    padding: 0.75rem 0.5rem 0.75rem 0.5rem;
    text-align: center;
}

.switch-back-btn {
    display: inline-flex;
    align-items: center;
    background: #f3f4f6;
    color: #6b7280;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 0.35em 0.75em;
    font-size: 0.95em;
    font-weight: 500;
    text-decoration: none;
    transition: background 0.2s, color 0.2s, border 0.2s;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.switch-back-btn svg {
    margin-right: 0.5em;
}

.switch-back-btn:hover, .switch-back-btn:focus {
    background: #e5e7eb;
    color: #374151;
    border-color: #d1d5db;
    outline: none;
}

.beta-badge {
    background: #f59e42;
    color: #ffffff;
    font-size: 0.7em;
    font-weight: bold;
    border-radius: 4px;
    padding: 0.1em 0.5em;
    margin-left: 0.75em;
    letter-spacing: 0.05em;
    vertical-align: middle;
}

</style>
