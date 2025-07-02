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
                        :bucket-actions="bucketActions"
                        @bucket-action="handleBucketAction"
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
        <UploadModal
            :visible="showUploadModal"
            :buckets="buckets"
            :max-upload-size="maxUploadSize"
            @close="showUploadModal = false"
            @upload-success="handleUploadSuccess"
        />

        <!-- Edit Modal -->
        <BaseModal
            v-if="showEditModal"
            :visible="true"
            title="Edit File"
            @close="closeEditModal"
        >
            <div class="form-group">
                <label for="filename_display">Filename</label>
                <input
                    type="text"
                    id="filename_display"
                    v-model="editingObject.filename_display"
                    placeholder="Enter display name"
                    :disabled="isEditing"
                />
            </div>
            <div class="form-group">
                <label>Metadata</label>
                <div class="form-sub-label">Add any custom information about this file, this will be searchable</div>
                <div class="metadata-editor">
                    <div class="metadata-list">
                        <div v-for="(item, index) in editingObject.metadata" :key="index" class="metadata-item">
                            <input
                                type="text"
                                v-model="item.key"
                                placeholder="Key"
                                :disabled="isEditing"
                                class="metadata-key"
                            />
                            <input
                                type="text"
                                v-model="item.value"
                                placeholder="Value"
                                :disabled="isEditing"
                                class="metadata-value"
                            />
                            <button
                                class="remove-button"
                                @click="removeMetadata(index)"
                                :disabled="isEditing"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <button
                        class="add-button"
                        @click="addMetadata"
                        :disabled="isEditing"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        Add Metadata
                    </button>
                </div>
            </div>

            <div v-if="editError" class="error-message">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                {{ editError }}
            </div>

            <div v-if="editSuccess" class="success-message">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Object updated successfully!
            </div>

            <template #footer>
                <Button 
                    variant="secondary" 
                    text="Cancel" 
                    icon="cancel" 
                    @click="closeEditModal" 
                    :disabled="isEditing"
                />
                <Button 
                    variant="primary" 
                    :text="isEditing ? 'Saving...' : 'Save Changes'" 
                    icon="save" 
                    @click="saveObjectEdit" 
                    :disabled="!editingObject?.filename_display || isEditing"
                    :loading="isEditing"
                />
            </template>
        </BaseModal>

        <!-- URL Copy Modal -->
        <BaseModal
            v-if="showUrlCopyModal"
            :visible="true"
            title="Copy URL"
            @close="closeUrlCopyModal"
        >
            <div class="form-group">
                <label>Source URL</label>
                <div class="url-input-group">
                    <textarea
                        v-model="urlToCopy.src"
                        readonly
                        rows="2"
                        ref="srcTextarea"
                    ></textarea>
                    <button class="copy-button" @click="copyUrlFromTextarea('src')" title="Copy source URL">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                    </button>
                </div>
                <div class="url-description">Use this URL to display the file in a webpage or embed it in content. If accessed directly the browser will attempt to render it in-window (works well for images and PDFs)</div>
            </div>
            <div class="form-group">
                <label>Download URL</label>
                <div class="url-input-group">
                    <textarea
                        v-model="urlToCopy.download"
                        readonly
                        rows="2"
                        ref="downloadTextarea"
                    ></textarea>
                    <button class="copy-button" @click="copyUrlFromTextarea('download')" title="Copy download URL">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                    </button>
                </div>
                <div class="url-description">Use this URL to force the browser to download the file instead of displaying it. The file will download to the user's device as "{{ urlToCopy.humanName || 'human-name.ext' }}"</div>
            </div>

            <div v-if="urlCopyError" class="error-message">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                {{ urlCopyError }}
            </div>

            <div v-if="urlCopySuccess" class="success-message">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                URL copied to clipboard!
            </div>

            <template #footer>
                <Button 
                    variant="secondary" 
                    text="Cancel" 
                    icon="cancel" 
                    @click="closeUrlCopyModal" 
                />
            </template>
        </BaseModal>

        <!-- Delete Confirmation Modal -->
        <BaseModal
            v-if="showDeleteModal"
            :visible="true"
            title="Delete File"
            @close="closeDeleteModal"
        >
            <FilePreview
                v-if="deletingObject"
                :file="deletingObject"
                :show-border="true"
                :margin-bottom="true"
            />
            <div class="warning-message">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <div class="warning-content">
                    <h4>Are you sure you want to delete this file?</h4>
                </div>
            </div>

            <div v-if="deleteError" class="error-message">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                {{ deleteError }}
                <a
                    v-if="deleteError.includes('Object is in use')"
                    :href="`${siteUrl}/admin/cdn/utilities/usages?object=${deletingObject.id}`"
                    target="_blank"
                    class="check-usages-button"
                >
                    Check for usages
                </a>
            </div>

            <div v-if="deleteSuccess" class="success-message">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Object deleted successfully!
            </div>

            <template #footer>
                <Button 
                    variant="secondary" 
                    text="Cancel" 
                    icon="cancel" 
                    @click="closeDeleteModal" 
                    :disabled="isDeleting"
                />
                <Button 
                    variant="danger" 
                    :text="isDeleting ? 'Deleting...' : 'Delete File'" 
                    icon="delete" 
                    @click="confirmDelete" 
                    :disabled="isDeleting"
                    :loading="isDeleting"
                />
            </template>
        </BaseModal>

        <replace-file-modal
            :visible="showReplaceModal"
            :object="replacingObject"
            :site-url="siteUrl"
            :max-upload-size="maxUploadSize"
            @close="showReplaceModal = false"
            @file-replaced="handleFileReplaced"
        />

        <move-copy-modal
            v-if="showMoveCopyModal"
            :visible="true"
            :object="moveCopyObject"
            :buckets="buckets"
            :site-url="siteUrl"
            @close="closeMoveCopyModal"
            @success="handleMoveCopySuccess"
        />

        <!-- Create Bucket Modal -->
        <BaseModal
            v-if="showCreateBucketModal"
            :visible="true"
            title="Create New Bucket"
            @close="closeCreateBucketModal"
        >
            <div class="form-group">
                <label for="bucket-name">Bucket Name</label>
                <input
                    type="text"
                    id="bucket-name"
                    v-model="newBucketName"
                    placeholder="Enter bucket name"
                    :disabled="isCreatingBucket"
                    @keyup.enter="createBucket"
                />
            </div>

            <div v-if="createBucketError" class="error-message">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                {{ createBucketError }}
            </div>

            <div v-if="createBucketSuccess" class="success-message">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Bucket created successfully!
            </div>
        
        <template #footer>
            <Button 
                variant="secondary" 
                text="Cancel" 
                icon="cancel" 
                @click="closeCreateBucketModal" 
                :disabled="isCreatingBucket"
            />
            <Button 
                variant="primary" 
                :text="isCreatingBucket ? 'Creating...' : 'Create Bucket'" 
                icon="add" 
                @click="createBucket" 
                :disabled="!newBucketName || isCreatingBucket"
                :loading="isCreatingBucket"
            />
        </template>
        </BaseModal>

        <!-- Delete Bucket Confirmation Modal -->
        <BaseModal
            v-if="showDeleteBucketModal"
            :visible="true"
            title="Delete Bucket"
            @close="closeDeleteBucketModal"
        >
            <div class="warning-message">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <div class="warning-content">
                    <h4>Are you sure you want to delete this bucket?</h4>
                    <p>This action cannot be undone. The following bucket will be deleted:</p>
                    <div class="object-details">
                        <strong>Name:</strong> {{ bucketToDelete?.label }}<br>
                        <strong>ID:</strong> {{ bucketToDelete?.id }}
                    </div>
                </div>
            </div>

            <div v-if="deleteBucketError" class="error-message">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                {{ deleteBucketError }}
            </div>

            <div v-if="deleteBucketSuccess" class="success-message">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Bucket deleted successfully!
            </div>

            <template #footer>
                <Button 
                    variant="secondary" 
                    text="Cancel" 
                    icon="cancel" 
                    @click="closeDeleteBucketModal" 
                    :disabled="isDeletingBucket"
                />
                <Button 
                    variant="danger" 
                    :text="isDeletingBucket ? 'Deleting...' : 'Delete Bucket'" 
                    icon="delete" 
                    @click="confirmDeleteBucket" 
                    :disabled="isDeletingBucket"
                    :loading="isDeletingBucket"
                />
            </template>
        </BaseModal>

        <!-- Trash Modal -->
        <BaseModal
            v-if="showTrashModal"
            :visible="true"
            title="Trash"
            @close="closeTrashModal"
        >
            <div v-if="loadingTrashedItems" class="loading-container">
                <div class="loading-spinner"></div>
                <p>Loading trashed items...</p>
            </div>

            <div v-else-if="trashedItems.length === 0" class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="empty-icon">
                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <p>No items in trash</p>
            </div>

            <div v-else class="trashed-items-list">
                <div class="trashed-items-header">
                    <div class="select-all-container">
                        <label class="select-all-label">
                            <input 
                                type="checkbox" 
                                :checked="selectedTrashedItems.length === trashedItems.length" 
                                @change="selectedTrashedItems = selectedTrashedItems.length === trashedItems.length ? [] : trashedItems.map(item => item.id)"
                            />
                            <span>Select All</span>
                        </label>
                    </div>
                    <div class="selection-info" v-if="selectedTrashedItems.length > 0">
                        {{ selectedTrashedItems.length }} item{{ selectedTrashedItems.length > 1 ? 's' : '' }} selected
                    </div>
                </div>

                <div class="trashed-items-container">
                    <FilePreview
                        v-for="item in trashedItems" 
                        :key="item.id" 
                        :file="item"
                        :show-checkbox="true"
                        :is-selected="selectedTrashedItems.includes(item.id)"
                        :container-class="'clickable'"
                        :show-border="false"
                        @click="toggleTrashedItem(item.id)"
                        @selection-change="(selected) => toggleTrashedItem(item.id)"
                    />
                </div>
            </div>

            <div v-if="restoreError" class="error-message modal-message">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                {{ restoreError }}
            </div>

            <div v-if="restoreSuccess" class="success-message modal-message">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Items restored successfully!
            </div>

            <template #footer>
                <Button 
                    variant="secondary" 
                    text="Cancel" 
                    icon="cancel" 
                    @click="closeTrashModal" 
                    :disabled="isRestoring"
                />
                <Button 
                    variant="primary" 
                    :text="isRestoring ? 'Restoring...' : 'Restore Selected'" 
                    icon="restore" 
                    @click="restoreTrashedItems" 
                    :disabled="selectedTrashedItems.length === 0 || isRestoring"
                    :loading="isRestoring"
                />
            </template>
        </BaseModal>
    </div>
</template>

<script>
import axios from 'axios';
import { debounce } from 'lodash'
import MultiSelect from './MultiSelect.vue'
import BucketSelector from './MediaManagerV2/BucketSelector.vue'
import ObjectListItem from './MediaManagerV2/ObjectListItem.vue'
import ObjectGridItem from './MediaManagerV2/ObjectGridItem.vue'
import ReplaceFileModal from './MediaManagerV2/ReplaceFileModal.vue'
import MoveCopyModal from './MediaManagerV2/MoveCopyModal.vue'
import Button from './MediaManagerV2/Button.vue'
import BaseModal from './MediaManagerV2/BaseModal.vue'
import FilePreview from './MediaManagerV2/FilePreview.vue'
import UploadModal from './MediaManagerV2/UploadModal.vue'

export default {
    name: 'MediaManagerV2',
    components: {
        MultiSelect,
        BucketSelector,
        ObjectListItem,
        ObjectGridItem,
        ReplaceFileModal,
        MoveCopyModal,
        Button,
        BaseModal,
        FilePreview,
        UploadModal
    },
    props: {
        maxUploadSize: {
            type: Number,
            default: 10485760 // 10MB in bytes
        },
        userCanCreateBucket: {
            type: Boolean,
            default: false
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
        },

        bucketActions() {
            return [
                {
                    id: 'delete',
                    title: 'Delete Bucket',
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>',
                    class: 'delete-action',
                    // Only show delete button for buckets with 0 objects
                    condition: (bucket) => {
                        // Parse the object_count string to get the number
                        // The format is "X objects" or "1 object"
                        const countStr = bucket.object_count;
                        if (!countStr) return false;

                        // Extract the number from the string
                        const match = countStr.match(/^(\d+)/);
                        if (!match) return false;

                        const count = parseInt(match[1]);
                        return count === 0;
                    }
                }
            ];
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

                // Format the object_count property to display as "X objects"
                this.buckets = buckets.map(bucket => {
                    // Create a new object to avoid modifying the original
                    const formattedBucket = { ...bucket };

                    // Format the object_count property
                    if (formattedBucket.object_count !== undefined) {
                        formattedBucket.object_count = `${formattedBucket.object_count} ${formattedBucket.object_count === 1 ? 'object' : 'objects'}`;
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

        async copyUrlFromTextarea(type) {
            try {
                await navigator.clipboard.writeText(this.urlToCopy[type]);
                this.urlCopySuccess = true;
                this.urlCopyError = null;
                setTimeout(() => {
                    this.urlCopySuccess = false;
                }, 2000);
            } catch (err) {
                this.urlCopyError = 'Failed to copy URL. Please try selecting and copying manually.';
                this.urlCopySuccess = false;
            }
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

        async handleBucketAction({ action, bucket }) {
            if (action.id === 'delete') {
                // Show delete confirmation modal
                this.bucketToDelete = bucket;
                this.showDeleteBucketModal = true;
                this.deleteBucketError = null;
                this.deleteBucketSuccess = false;
            }
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

        handleFileSelect(event) {
            const files = Array.from(event.target.files);

            // Validate file sizes
            const invalidFiles = files.filter(file => file.size > this.maxUploadSize);
            if (invalidFiles.length > 0) {
                this.error = `Some files exceed the maximum allowed size of ${this.formatFileSize(this.maxUploadSize)}`;
                return;
            }

            this.filesToUpload = [...this.filesToUpload, ...files];
            event.target.value = ''; // Reset file input
        },

        removeFile(index) {
            this.filesToUpload.splice(index, 1);
        },

        async uploadFiles() {
            if (this.filesToUpload.length === 0 || !this.selectedUploadBucket.length || this.isUploading) {
                return;
            }

            try {
                this.isUploading = true;
                this.uploadError = null;
                this.uploadSuccess = false;
                this.uploadProgress = {};
                this.overallProgress = 0;

                // Upload each file individually
                const uploadPromises = this.filesToUpload.map(async (file, index) => {
                    // Initialize progress for this file
                    this.$set(this.uploadProgress, index, 0);

                    // Create FormData for the upload
                    const formData = new FormData();
                    formData.append('upload', file);

                    // Send the upload request with proper headers and progress tracking
                    const response = await axios.post(
                        `${this.siteUrl}api/cdn/object/create`,
                        formData,
                        {
                            headers: {
                                'Content-Type': 'multipart/form-data',
                                'X-Cdn-Bucket': this.selectedUploadBucket[0] // Use first item from array
                            },
                            onUploadProgress: (progressEvent) => {
                                // Update progress for this file
                                const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                                this.$set(this.uploadProgress, index, percentCompleted);

                                // Calculate overall progress
                                const totalProgress = Object.values(this.uploadProgress).reduce((sum, value) => sum + value, 0);
                                this.overallProgress = totalProgress / this.filesToUpload.length;
                            }
                        }
                    );

                    return response.data.object;
                });

                // Wait for all uploads to complete
                const uploadedObjects = await Promise.all(uploadPromises);

                // Handle successful upload
                console.log('Upload successful:', uploadedObjects);

                // Show success message
                this.uploadSuccess = true;

                // Clear the file list
                this.filesToUpload = [];

                // Reset all filters
                this.keywords = null;
                this.selectedBuckets = [];
                this.selectedFileTypes = [];
                this.selectedUploaders = [];
                this.dateLower = null;
                this.dateUpper = null;
                this.page = 1;

                // Refresh the object list
                this.doSearch();

                // Close the modal after a delay
                setTimeout(() => {
                    this.showUploadModal = false;
                    this.uploadSuccess = false;
                    this.uploadProgress = {};
                    this.overallProgress = 0;
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
            const files = Array.from(event.dataTransfer.files);

            // Validate file sizes
            const invalidFiles = files.filter(file => file.size > this.maxUploadSize);
            if (invalidFiles.length > 0) {
                this.error = `Some files exceed the maximum allowed size of ${this.formatFileSize(this.maxUploadSize)}`;
                return;
            }

            this.filesToUpload = [...this.filesToUpload, ...files];
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

        addMetadata() {
            if (!this.editingObject.metadata) {
                this.editingObject.metadata = [];
            }
            this.editingObject.metadata.push({ key: '', value: '' });
        },

        removeMetadata(index) {
            this.editingObject.metadata.splice(index, 1);
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

        handleMoveCopySuccess({ action, object }) {
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

        validateFile(file) {
            if (file.size > this.maxUploadSize) {
                return `File size exceeds the maximum allowed size of ${this.formatFileSize(this.maxUploadSize)}`;
            }
            return null;
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

                const { success, error } = response.data.data;

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

    &.is-modal {
        .sidebar {
            /* No special styling needed for modal mode */
        }
    }

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
        display: flex;
        flex-direction: column;
        position: relative;
        height: 100vh; /* Full viewport height */
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
        background: #ffffff;
        text-align: center;
        position: relative;

        h3 {
            margin: 0 auto;
            font-size: 20px;
            font-weight: 600;
            color: #111827;
            padding: 0 !important;
            border: none !important;
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
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);

            &:hover {
                background-color: rgba(243, 244, 246, 0.8);
                color: #4f46e5;
                transform: translateY(-50%) rotate(90deg);
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
                background: #f3f4f6;
                border: 1px solid #e5e7eb;
                color: #4b5563;

                &:hover:not(:disabled) {
                    background: #e5e7eb;
                    color: #374151;
                }

                &:active:not(:disabled) {
                    background: #d1d5db;
                }

                &:disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
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

.progress-bar {
    height: 6px;
    background-color: #e5e7eb;
    border-radius: 3px;
    overflow: hidden;
    margin-top: 6px;
    position: relative;

    &__fill {
        height: 100%;
        background: linear-gradient(90deg, #4f46e5, #7c3aed);
        border-radius: 3px;
        transition: width 0.2s ease;
    }

    &__text {
        position: absolute;
        right: 0;
        top: -18px;
        font-size: 11px;
        color: #6b7280;
    }
}

.overall-progress {
    flex: 1;
    margin-right: 16px;

    &__bar {
        height: 8px;
        background-color: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 6px;
    }

    &__fill {
        height: 100%;
        background: linear-gradient(90deg, #4f46e5, #7c3aed);
        border-radius: 4px;
        transition: width 0.3s ease;
    }

    &__text {
        font-size: 12px;
        color: #6b7280;
    }
}

.footer-buttons {
    display: flex;
    gap: 12px;
}

.edit-modal {
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

    &__container {
        position: relative;
        width: 90%;
        max-width: 500px;
        background-color: white;
        border-radius: 16px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: containerSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        transform: translateY(20px);
    }

    &__header {
        padding: 20px 24px;
        border-bottom: 1px solid rgba(224, 224, 224, 0.6);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #ffffff;
        text-align: center;
        position: relative;

        h3 {
            margin: 0 auto;
            font-size: 20px;
            font-weight: 600;
            color: #111827;
            padding: 0 !important;
            border: none !important;
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
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);

            &:hover {
                background-color: rgba(243, 244, 246, 0.8);
                color: #4f46e5;
                transform: translateY(-50%) rotate(90deg);
            }
        }
    }

    &__body {
        padding: 24px;
        background: linear-gradient(to bottom, #ffffff, #f9fafb);

        .form-group {
            margin-bottom: 24px;

            label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
                color: #111827;
                font-size: 15px;
            }

            input {
                width: 100%;
                padding: 12px 16px;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                font-size: 14px;
                background-color: white;
                transition: all 0.2s ease;
                color: #374151;

                &:focus {
                    outline: none;
                    border-color: #4f46e5;
                    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
                }

                &:disabled {
                    background-color: #f3f4f6;
                    cursor: not-allowed;
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

                &:hover:not(:disabled) {
                    background-color: #f3f4f6;
                    border-color: #9ca3af;
                }

                &:disabled {
                    opacity: 0.7;
                    cursor: not-allowed;
                }
            }

            &.save-button {
                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
                border: none;
                color: white;
                box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2), 0 2px 4px -1px rgba(79, 70, 229, 0.1);

                &:hover:not(:disabled) {
                    background: linear-gradient(135deg, #4338ca 0%, #6d28d9 100%);
                    transform: translateY(-1px);
                    box-shadow: 0 6px 10px -1px rgba(79, 70, 229, 0.3), 0 2px 4px -1px rgba(79, 70, 229, 0.2);
                }

                &:active:not(:disabled) {
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

/* Global Modal Message Styles */
.modal-message {
    margin-top: 16px;
    margin-bottom: 1rem;
    padding: 12px 16px;
    border-radius: 0.375rem;
    display: flex;
    align-items: center;
    border-left: 4px solid;
    animation: messageSlideIn 0.3s ease forwards;
    font-size: 14px;
}

.success-message {
    background-color: rgba(220, 252, 231, 0.6);
    color: #15803d;
    border-color: #22c55e;
}

.success-message svg {
    color: #22c55e;
    height: 1.25rem;
    width: 1.25rem;
    margin-right: 0.5rem;
}

.error-message {
    background-color: rgba(254, 226, 226, 0.6);
    color: #b91c1c;
    border-color: #ef4444;
    display: flex;
    align-items: center;
    gap: 12px;

    .check-usages-button {
        margin-left: auto;
        padding: 4px 12px;
        background-color: white;
        border: 1px solid #ef4444;
        border-radius: 6px;
        color: #b91c1c;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s ease;

        &:hover {
            background-color: #fee2e2;
            border-color: #dc2626;
            color: #991b1b;
        }
    }
}

.error-message svg {
    color: #ef4444;
    height: 1.25rem;
    width: 1.25rem;
    margin-right: 0.5rem;
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

.url-copy-modal {
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

    &__container {
        position: relative;
        width: 90%;
        max-width: 500px;
        background-color: white;
        border-radius: 16px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: containerSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        transform: translateY(20px);
    }

    &__header {
        padding: 20px 24px;
        border-bottom: 1px solid rgba(224, 224, 224, 0.6);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #ffffff;
        text-align: center;
        position: relative;

        h3 {
            margin: 0 auto;
            font-size: 20px;
            font-weight: 600;
            color: #111827;
            padding: 0 !important;
            border: none !important;
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
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);

            &:hover {
                background-color: rgba(243, 244, 246, 0.8);
                color: #4f46e5;
                transform: translateY(-50%) rotate(90deg);
            }
        }
    }

    &__body {
        padding: 24px;
        background: linear-gradient(to bottom, #ffffff, #f9fafb);

        .form-group {
            margin-bottom: 24px;

            &:last-child {
                margin-bottom: 0;
            }

            label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
                color: #111827;
                font-size: 15px;
                display: flex;
                align-items: center;
                gap: 8px;

                &::after {
                    content: '';
                    flex: 1;
                    height: 1px;
                    background: linear-gradient(to right, #d1d5db, transparent);
                }
            }

            .url-input-group {
                position: relative;
                display: flex;
                align-items: flex-start;
                gap: 8px;

                textarea {
                    flex: 1;
                    padding: 12px 16px;
                    border: 1px solid #d1d5db;
                    border-radius: 8px;
                    font-size: 14px;
                    background-color: white;
                    transition: all 0.2s ease;
                    color: #374151;
                    resize: vertical;
                    min-height: 60px;

                    &:focus {
                        outline: none;
                        border-color: #4f46e5;
                        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
                    }
                }

                .copy-button {
                    background: none;
                    border: 1px solid #d1d5db;
                    border-radius: 8px;
                    padding: 12px;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #6b7280;
                    flex-shrink: 0;

                    &:hover {
                        background-color: #f3f4f6;
                        border-color: #9ca3af;
                        color: #4f46e5;
                    }

                    &:active {
                        background-color: #e5e7eb;
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
            background-color: white;
            border: 1px solid #d1d5db;
            color: #4b5563;

            &:hover {
                background-color: #f3f4f6;
                border-color: #9ca3af;
            }
        }
    }
}

.url-description {
    font-size: 13px;
    color: #6b7280;
    margin-top: 6px;
    line-height: 1.4;
    font-style: italic;
}

.delete-modal {
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
        max-width: 500px;
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
        background: #ffffff;
        text-align: center;
        position: relative;

        h3 {
            margin: 0 auto;
            font-size: 20px;
            font-weight: 600;
            color: #111827;
            padding: 0 !important;
            border: none !important;
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
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);

            &:hover {
                background-color: rgba(243, 244, 246, 0.8);
                color: #4f46e5;
                transform: translateY(-50%) rotate(90deg);
            }
        }
    }

    &__body {
        padding: 24px;
        background: linear-gradient(to bottom, #ffffff, #f9fafb);

        .warning-message {
            display: flex;
            gap: 16px;
            padding: 16px;
            background-color: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 8px;
            margin-bottom: 24px;

            svg {
                color: #ea580c;
                width: 24px;
                height: 24px;
                flex-shrink: 0;
            }

            .warning-content {
                h4 {
                    margin: 0 0 8px;
                    color: #c2410c;
                    font-size: 16px;
                    font-weight: 600;
                }

                p {
                    margin: 0 0 12px;
                    color: #431407;
                    font-size: 14px;
                    line-height: 1.5;
                }

                .object-details {
                    background-color: white;
                    padding: 12px;
                    border-radius: 6px;
                    font-size: 14px;
                    line-height: 1.6;
                    color: #431407;

                    strong {
                        color: #c2410c;
                    }
                }
            }
        }

        .troubleshooting-actions {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;

            h4 {
                margin: 0 0 16px;
                color: #111827;
                font-size: 16px;
                font-weight: 600;
            }

            .action-buttons {
                display: flex;
                gap: 12px;

                .action-button {
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
                    }

                    &:hover {
                        background-color: #f3f4f6;
                        border-color: #d1d5db;
                        color: #111827;
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

                &:hover:not(:disabled) {
                    background-color: #f3f4f6;
                    border-color: #9ca3af;
                }

                &:disabled {
                    opacity: 0.7;
                    cursor: not-allowed;
                }
            }

            &.delete-button {
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
                border: none;
                color: white;
                box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2), 0 2px 4px -1px rgba(239, 68, 68, 0.1);

                &:hover:not(:disabled) {
                    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
                    transform: translateY(-1px);
                    box-shadow: 0 6px 10px -1px rgba(239, 68, 68, 0.3), 0 2px 4px -1px rgba(239, 68, 68, 0.2);
                }

                &:active:not(:disabled) {
                    transform: translateY(1px);
                }

                &:disabled {
                    background: linear-gradient(135deg, #fecaca 0%, #fee2e2 100%);
                    cursor: not-allowed;
                    box-shadow: none;
                }
            }
        }
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

.selected-object-info {
    display: inline-flex;
    align-items: center;
    margin-left: 1rem;
    padding: 0.5rem 1rem;
    background-color: #f3f4f6;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    color: #4b5563;

    &__label {
        font-weight: 500;
        margin-right: 0.5rem;
    }

    &__name {
        font-weight: 600;
        color: #1f2937;
        margin-right: 0.5rem;
    }

    &__details {
        color: #6b7280;
    }
}

.form-sub-label {
    color: #6b7280;
    font-size: 13px;
    margin-bottom: 8px;
}

.metadata-editor {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 8px;
    margin-top: 4px;

    .metadata-item {
        display: flex;
        gap: 6px;
        align-items: center;

        .metadata-key,
        .metadata-value {
            flex: 1;
            padding: 4px 8px;
            margin-bottom: 4px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-size: 13px;
            background: white;
            transition: all 0.2s ease;
            height: 28px;

            &:focus {
                outline: none;
                border-color: #4f46e5;
                box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
            }

            &:disabled {
                background: #f3f4f6;
                cursor: not-allowed;
            }
        }

        .remove-button {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            padding: 0;
            margin-bottom: 4px;
            border: none;
            background: none;
            color: #6b7280;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.2s ease;
            flex-shrink: 0;

            svg {
                width: 14px;
                height: 14px;
            }

            &:hover {
                background: #fee2e2;
                color: #b91c1c;
            }

            &:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
        }
    }

    .add-button {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border: 1px solid #e5e7eb;
        border-radius: 4px;
        background: white;
        color: #4b5563;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s ease;

        svg {
            width: 16px;
            height: 16px;
        }

        &:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }

        &:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    }
}

/* Modal Styles */
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

.modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
}



/* Form Elements */
.form-group {
    margin-bottom: 1rem;

    label {
        display: block;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #374151;
    }

    .form-sub-label {
        font-size: 0.75rem;
        color: #6b7280;
        margin-bottom: 0.5rem;
    }

    input[type="text"] {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        font-size: 0.875rem;
        color: #111827;

        &:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }

        &:disabled {
            background: #f3f4f6;
            cursor: not-allowed;
        }
    }
}

/* Upload Zone */
.upload-zone {
    border: 2px dashed #e5e7eb;
    border-radius: 6px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #f9fafb;
    margin-bottom: 1rem;

    &:hover {
        border-color: #6b7280;
        background: #f3f4f6;
    }

    &.drag-over {
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

/* File List */
.file-list {
    margin-top: 1rem;

    h4 {
        margin: 0 0 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: #4b5563;
    }

    .file-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        margin-bottom: 0.5rem;

        .file-info {
            flex: 1;

            .file-name {
                display: block;
                font-size: 0.875rem;
                color: #111827;
                margin-bottom: 0.25rem;
            }

            .file-size {
                font-size: 0.75rem;
                color: #6b7280;
            }

            .progress-bar {
                margin-top: 0.5rem;
                height: 0.5rem;
                background: #e5e7eb;
                border-radius: 0.25rem;
                overflow: hidden;
                position: relative;

                &__fill {
                    height: 100%;
                    background: #4f46e5;
                    transition: width 0.3s ease;
                }

                &__text {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    font-size: 0.75rem;
                    color: white;
                    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
                }
            }
        }

        .remove-button {
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
}

/* Overall Progress */
.overall-progress {
    width: 100%;
    margin-bottom: 1rem;

    &__bar {
        height: 0.5rem;
        background: #e5e7eb;
        border-radius: 0.25rem;
        overflow: hidden;
    }

    &__fill {
        height: 100%;
        background: #4f46e5;
        transition: width 0.3s ease;
    }

    &__text {
        display: block;
        margin-top: 0.5rem;
        font-size: 0.75rem;
        color: #6b7280;
        text-align: center;
    }
}

/* Buttons */
.cancel-button,
.upload-button,
.save-button,
.restore-button,
.delete-button,
.close-button {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;

    &:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    svg {
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
    }
}

.cancel-button {
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    color: #4b5563;

    &:hover:not(:disabled) {
        background: #e5e7eb;
        color: #374151;
    }

    &:active:not(:disabled) {
        background: #d1d5db;
    }
}

.upload-button,
.save-button,
.restore-button {
    background: #4f46e5;
    border: 1px solid transparent;
    color: white;

    &:hover:not(:disabled) {
        background: #4338ca;
    }

    &:active:not(:disabled) {
        background: #3730a3;
    }
}

.delete-button {
    background: #ef4444;
    border: 1px solid transparent;
    color: white;

    &:hover:not(:disabled) {
        background: #dc2626;
    }

    &:active:not(:disabled) {
        background: #b91c1c;
    }
}

/* Messages */
.error-message,
.success-message {
    margin-top: 1rem;
    padding: 0.75rem;
    border-radius: 6px;
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

.error-message {
    background: #fef2f2;
    border: 1px solid #fee2e2;
    color: #b91c1c;
}

.success-message {
    background: #ecfdf5;
    border: 1px solid #d1fae5;
    color: #047857;
}

/* Warning Message */
.warning-message {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    background: #fffbeb;
    border: 1px solid #fef3c7;
    border-radius: 6px;
    margin-bottom: 1rem;

    svg {
        width: 1.5rem;
        height: 1.5rem;
        color: #d97706;
        flex-shrink: 0;
    }

    .warning-content {
        h4 {
            margin: 0 0 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #92400e;
        }

        p {
            margin: 0 0 0.5rem;
            font-size: 0.875rem;
            color: #78350f;
        }

        .object-details {
            color: #78350f;
            line-height: 1.5;
        }
    }
}

/* URL Copy */
.url-input-group {
    position: relative;
    margin-bottom: 0.5rem;

    textarea {
        width: 100%;
        padding: 0.5rem;
        padding-right: 2.5rem;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        font-size: 0.875rem;
        color: #111827;
        resize: none;
        background: #f9fafb;

        &:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }
    }

    .copy-button {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
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

.url-description {
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 1rem;
}

.check-usages-button {
    display: inline-block;
    margin-left: 0.5rem;
    color: #4f46e5;
    text-decoration: none;
    font-weight: 500;

    &:hover {
        text-decoration: underline;
    }
}

.hidden {
    display: none;
}

/* Trash Modal Styles */
.loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    text-align: center;

    .loading-spinner {
        margin-bottom: 1rem;
    }

    p {
        color: #6b7280;
        font-size: 0.875rem;
    }
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

.trashed-items-list {
    display: flex;
    flex-direction: column;

    .trashed-items-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 1rem;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-top-left-radius: 6px;
        border-top-right-radius: 6px;

        .select-all-container {
            display: flex;
            align-items: center;

            .select-all-label {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.875rem;
                color: #4b5563;
                cursor: pointer;

                input[type="checkbox"] {
                    width: 1rem;
                    height: 1rem;
                }
            }
        }

        .selection-info {
            font-size: 0.75rem;
            color: #6b7280;
            background: #e5e7eb;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
        }
    }

    .trashed-items-container {
        border: 1px solid #e5e7eb;
        border-top: none;
        border-bottom-left-radius: 6px;
        border-bottom-right-radius: 6px;
        max-height: 400px;
        overflow-y: auto;
        background: #ffffff;

        /* Table-like styling for FilePreview components */
        .file-preview {
            border-bottom: 1px solid #e5e7eb;
            border-radius: 0;
            margin: 0;
            padding: 0.75rem 1rem;
            transition: background-color 0.15s ease;

            &:last-child {
                border-bottom: none;
            }

            &:nth-child(even) {
                background-color: #f9fafb;
            }

            &:nth-child(odd) {
                background-color: #ffffff;
            }

            &:hover {
                background-color: #f3f4f6 !important;
                border-color: #d1d5db;
            }

            &.selected {
                background-color: #eef2ff !important;
                border-left: 3px solid #4f46e5;
            }

            &.selected:nth-child(even) {
                background-color: #eef2ff !important;
            }
        }
    }
}

/* This style is now handled by the upload-button class */
</style>
