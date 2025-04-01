<template>
    <tr>
        <td class="id-cell">{{ item.id }}</td>
        <td class="thumbnail-cell">
            <img
                v-if="item.is_img"
                :src="item.url.thumb.list"
                :alt="item.file.name.human"
                class="thumbnail"
            />
            <div v-else class="file-icon">
                <span>{{ item.file.ext.toUpperCase() }}</span>
            </div>
        </td>
        <td class="file-name-cell">
            {{ item.file.name.human }}
            <br><span class="bucket">{{ item.bucket.label }}</span>
        </td>
        <td class="file-type-cell">
            <span :title="item.file.mime">{{ item.group }}</span>
        </td>
        <td class="file-size-cell">{{ item.file.size.human }}</td>
        <td class="created-cell">{{ item.created_by?.name || 'Unknown' }}</td>
        <td class="uploader-cell">{{ item.created.formatted }}</td>
        <td class="actions-cell">
            <div class="actions-dropdown">
                <div class="action-button">
                    <button class="action-button__main" @click="handleAction(hasCallback ? 'insert' : 'download')">
                        <div class="action-button__content">
                            <svg v-if="hasCallback" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ hasCallback ? 'Insert' : 'Download' }}</span>
                        </div>
                    </button>
                    <div class="action-button__divider"></div>
                    <button class="action-button__trigger" @click="toggleDropdown">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
                <div class="actions-menu" v-if="isOpen" @click.away="isOpen = false">
                    <div class="actions-menu__header">
                        <span>More actions</span>
                        <button class="close-button" @click="isOpen = false">&times;</button>
                    </div>
                    <div class="actions-menu__items">
                        <button 
                            v-if="hasCallback" 
                            class="action-item" 
                            @click="handleAction('insert')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                            </svg>
                            <span>Insert</span>
                        </button>
                        <button class="action-item" @click="handleAction('edit')">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                            </svg>
                            <span>Edit</span>
                        </button>
                        <button class="action-item" @click="handleAction('download')">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                            <span>Download</span>
                        </button>
                        <button class="action-item" @click="handleAction('copy-url')">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                                <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z" />
                            </svg>
                            <span>Copy URL</span>
                        </button>
                        <button class="action-item action-item--danger" @click="handleAction('delete')">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <span>Delete</span>
                        </button>
                    </div>
                </div>
            </div>
        </td>
    </tr>
</template>

<script>
export default {
    name: 'ObjectListItem',
    props: {
        item: {
            type: Object,
            required: true
        },
        hasCallback: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            isOpen: false
        }
    },
    mounted() {
        document.addEventListener('click', this.handleClickOutside);
        document.addEventListener('keydown', this.handleKeydown);
    },
    beforeDestroy() {
        document.removeEventListener('click', this.handleClickOutside);
        document.removeEventListener('keydown', this.handleKeydown);
    },
    methods: {
        toggleDropdown(event) {
            event.stopPropagation();
            this.isOpen = !this.isOpen;
        },
        handleAction(action) {
            this.$emit('action', {action, item: this.item});
            this.isOpen = false;
        },
        handleClickOutside(event) {
            const dropdown = this.$el.querySelector('.actions-dropdown');
            if (dropdown && !dropdown.contains(event.target)) {
                this.isOpen = false;
            }
        },
        handleKeydown(event) {
            if (event.key === 'Escape' && this.isOpen) {
                this.isOpen = false;
            }
        }
    }
}
</script>

<style lang="scss" scoped>
.thumbnail {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 4px;
}

.file-icon {
    width: 60px;
    height: 60px;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    color: #666666;
}

.bucket {
    background: #f0f1f4;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    margin-top: 0.35rem;
    display: inline-block;
}

.action-select {
    padding: 6px;
    border: 1px solid #dddddd;
    border-radius: 4px;
    background: #ffffff;
    width: 100%;

    &:focus {
        outline: none;
        border-color: #1a73e8;
    }
}

// Responsive adjustments
@media (max-width: 768px) {
    .thumbnail {
        width: 40px;
        height: 40px;
    }

    .file-icon {
        width: 40px;
        height: 40px;
    }
}

.actions-dropdown {
    position: relative;
}

.action-button {
    display: flex;
    align-items: center;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    overflow: hidden;
    width: 100%;

    &__main {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: none;
        color: #4b5563;
        border: none;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
        flex: 1;

        &:hover {
            background: #e5e7eb;
            color: #374151;
        }

        &:active {
            background: #d1d5db;
        }
    }

    &__content {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    &__divider {
        width: 1px;
        height: 20px;
        background: #e5e7eb;
        flex-shrink: 0;
    }

    &__trigger {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 6px 8px;
        background: none;
        border: none;
        color: #6b7280;
        cursor: pointer;
        transition: all 0.2s ease;
        width: 32px;
        flex-shrink: 0;

        svg {
            width: 16px;
            height: 16px;
            transition: transform 0.2s ease;
        }

        &:hover {
            background: #e5e7eb;
            color: #374151;
        }

        &:active {
            background: #d1d5db;
        }
    }

    svg {
        width: 16px;
        height: 16px;
    }
}

.actions-menu {
    position: absolute;
    top: 100%;
    right: 0;
    width: 200px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    z-index: 50;
    margin-top: 4px;
    overflow: hidden;
    border: 1px solid #e5e7eb;

    &__header {
        padding: 8px 12px;
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: space-between;

        span {
            font-size: 13px;
            font-weight: 600;
            color: #4b5563;
        }

        .close-button {
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 16px;
            line-height: 1;

            &:hover {
                background-color: #e5e7eb;
                color: #4b5563;
            }
        }
    }

    &__items {
        padding: 4px;
    }

    .action-item {
        width: 100%;
        padding: 8px 12px;
        border: none;
        background: none;
        display: flex;
        align-items: center;
        gap: 8px;
        color: #4b5563;
        font-size: 13px;
        cursor: pointer;
        border-radius: 6px;
        transition: all 0.2s ease;

        svg {
            width: 16px;
            height: 16px;
            color: #6b7280;
        }

        &:hover {
            background-color: #f3f4f6;
            color: #4f46e5;

            svg {
                color: #4f46e5;
            }
        }

        &--danger {
            color: #dc2626;

            svg {
                color: #dc2626;
            }

            &:hover {
                background-color: #fee2e2;
                color: #b91c1c;

                svg {
                    color: #b91c1c;
                }
            }
        }
    }
}
</style> 

