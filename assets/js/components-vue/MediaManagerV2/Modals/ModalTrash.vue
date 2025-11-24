<template>
    <ModalBase
        :visible="visible"
        title="Trash"
        :locked="isRestoring || isPurging"
        @close="handleClose"
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
                            @change="toggleSelectAll"
                        />
                        <span>Select All</span>
                    </label>
                </div>
                <div
                    class="selection-info"
                    :style="{ visibility: selectedTrashedItems.length > 0 ? 'visible' : 'hidden' }">
                    {{ selectedTrashedItems.length > 0 ? `${selectedTrashedItems.length} item${selectedTrashedItems.length > 1 ? 's' : ''} selected` : '0 items selected' }}
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
                    @selection-change="() => toggleTrashedItem(item.id)"
                />
            </div>
        </div>
        <Status
            v-if="restoreError"
            type="error"
            :message="restoreError"
        />
        <Status
            v-if="restoreSuccess"
            type="success"
            message="Items restored successfully!"
        />
        <Status
            v-if="purgeError"
            type="error"
            :message="purgeError"
        />
        <Status
            v-if="purgeSuccess"
            type="success"
            message="Items purged successfully!"
        />
        <template #footer>
            <div
                :class="{
                    'safari-repaint-fix': true,
                    'run-fix': isRestoring || isPurging
                }"
            >
                <Button
                    variant="secondary"
                    text="Cancel"
                    icon="cancel"
                    @click="handleClose"
                    :disabled="isRestoring || isPurging"
                />
                <Button
                    v-if="userPermissions.object.restore"
                    variant="primary"
                    :text="isRestoring ? 'Restoring...' : 'Restore Selected'"
                    icon="restore"
                    @click="handleRestore"
                    :disabled="selectedTrashedItems.length === 0 || isRestoring || isPurging"
                    :loading="isRestoring"
                />
                <Button
                    v-if="userPermissions.object.purge"
                    variant="danger"
                    :text="isPurging ? `Purging... (${purgeProgress}%)` : 'Purge Selected'"
                    icon="delete"
                    @click="handlePurge"
                    :disabled="selectedTrashedItems.length === 0 || isRestoring || isPurging"
                    :loading="isPurging"
                />
            </div>
        </template>
    </ModalBase>
</template>

<script>
import ModalBase from './ModalBase.vue';
import Button from '../Button.vue';
import FilePreview from '../FilePreview.vue';
import Status from '../Status.vue';
import axios from 'axios';

export default {
    name: 'ModalTrash',
    components: {ModalBase, Button, FilePreview, Status},
    inject: ['cdnApi', 'userPermissions'],
    props: {
        visible: {type: Boolean, default: false},
        trashedItems: {type: Array, default: () => []},
        selectedTrashedItems: {type: Array, default: () => []},
        loadingTrashedItems: {type: Boolean, default: false},
        isRestoring: {type: Boolean, default: false},
        restoreError: {type: String, default: null},
        restoreSuccess: {type: Boolean, default: false}
    },
    data() {
        return {
            isPurging: false,
            purgeError: null,
            purgeSuccess: false,
            purgeProgress: 0,
            purgeErrorList: [],
        };
    },
    methods: {
        handleClose() {
            this.$emit('close');
        },
        handleRestore() {
            this.$emit('restore');
        },
        toggleTrashedItem(itemId) {
            this.$emit('toggle-selection', itemId);
        },
        toggleSelectAll() {
            this.$emit('toggle-select-all');
        },
        async handlePurge() {
            if (this.selectedTrashedItems.length === 0) return;
            if (!window.confirm(`Are you sure you want to permanently delete ${this.selectedTrashedItems.length} item(s)? This cannot be undone.`)) return;
            this.isPurging = true;
            this.purgeError = null;
            this.purgeSuccess = false;
            this.purgeProgress = 0;
            this.purgeErrorList = [];

            // Create a copy of the selected items to iterate through
            const itemsToPurge = [...this.selectedTrashedItems];
            const totalItems = itemsToPurge.length;
            const successfullyPurged = [];

            for (let i = 0; i < itemsToPurge.length; i++) {
                const objectId = itemsToPurge[i];
                try {
                    await axios.post(this.cdnApi.object.delete(), {object_id: objectId});
                    successfullyPurged.push(objectId);
                } catch (err) {
                    this.purgeErrorList.push(objectId);
                }
                this.purgeProgress = Math.round(((i + 1) / totalItems) * 100);
            }

            // Update the arrays after all operations are complete
            for (const objectId of successfullyPurged) {
                // Remove purged item from trashedItems
                const idx = this.trashedItems.findIndex(item => item.id === objectId);
                if (idx !== -1) this.trashedItems.splice(idx, 1);

                // Remove purged item from selectedTrashedItems
                const selIdx = this.selectedTrashedItems.indexOf(objectId);
                if (selIdx !== -1) this.selectedTrashedItems.splice(selIdx, 1);
            }

            this.isPurging = false;
            if (this.purgeErrorList.length > 0) {
                this.purgeError = `Failed to purge ${this.purgeErrorList.length} item(s).`;
            } else {
                this.purgeSuccess = true;
                // Automatically dismiss success message after 1.5 seconds
                setTimeout(() => {
                    this.purgeSuccess = false;
                }, 1500);
            }
            this.$emit('purge');
        }
    }
};
</script>

<style scoped>
.loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    text-align: center;
}

.loading-spinner {
    margin-bottom: 1rem;
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

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    text-align: center;
    color: #6b7280;
}

.empty-icon {
    width: 3rem;
    height: 3rem;
    color: #9ca3af;
    margin-bottom: 1rem;
}

.trashed-items-list {
    display: flex;
    flex-direction: column;
    margin-bottom: 1rem;
}

.trashed-items-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-top-left-radius: 6px;
    border-top-right-radius: 6px;
}

.select-all-container {
    display: flex;
    align-items: center;
}

.select-all-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: #4b5563;
    cursor: pointer;
}

.select-all-label input[type="checkbox"] {
    width: 1rem;
    height: 1rem;
}

.selection-info {
    font-size: 0.75rem;
    color: #6b7280;
    background: #e5e7eb;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
}

.trashed-items-container {
    border: 1px solid #e5e7eb;
    border-top: none;
    border-bottom-left-radius: 6px;
    border-bottom-right-radius: 6px;
    max-height: 400px;
    overflow-y: auto;
    background: #ffffff;
}

/* Table-like styling for FilePreview components */
.trashed-items-container :deep(.file-preview) {
    border-bottom: 1px solid #e5e7eb;
    border-radius: 0;
    margin: 0;
    padding: 0.75rem 1rem;
    border: none !important;
    box-shadow: none !important;
    transition: background-color 0.15s ease;
}

.trashed-items-container :deep(.file-preview:last-child) {
    border-bottom: none;
}

.trashed-items-container :deep(.file-preview:nth-child(even)) {
    background-color: #f9fafb;
}

.trashed-items-container :deep(.file-preview:nth-child(odd)) {
    background-color: #ffffff;
}

.trashed-items-container :deep(.file-preview:hover),
.trashed-items-container :deep(.file-preview.selected) {
    border: none !important;
    box-shadow: none !important;
}

.trashed-items-container :deep(.file-preview.selected) {
    background-color: #eef2ff !important;
    border-left: 3px solid #4f46e5;
}

.trashed-items-container :deep(.file-preview.selected:nth-child(even)) {
    background-color: #eef2ff !important;
}
</style> 
