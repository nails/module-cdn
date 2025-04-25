<template>
    <div class="actions-dropdown" :class="theme">
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
                <button class="action-item" @click="handleAction('replace')">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                    </svg>
                    <span>Replace File</span>
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
</template>

<script>
export default {
    name: 'ObjectActions',
    props: {
        hasCallback: {
            type: Boolean,
            default: false
        },
        theme: {
            type: String,
            default: 'light',
            validator: value => ['light', 'dark'].includes(value)
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
            this.$emit('action', action);
            this.isOpen = false;
        },
        handleClickOutside(event) {
            const dropdown = this.$el;
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
.actions-dropdown {
    position: relative;
    margin-top: 8px;

    &.light {
        .action-button {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;

            &__main {
                color: #4b5563;

                &:hover {
                    background: #e5e7eb;
                    color: #374151;
                }

                &:active {
                    background: #d1d5db;
                }
            }

            &__divider {
                background: #e5e7eb;
            }

            &__trigger {
                color: #6b7280;

                &:hover {
                    background: #e5e7eb;
                    color: #374151;
                }

                &:active {
                    background: #d1d5db;
                }
            }
        }

        .actions-menu {
            background: white;
            border: 1px solid #e5e7eb;

            .actions-menu__header {
                background: #f9fafb;
                border-bottom: 1px solid #e5e7eb;

                span {
                    color: #4b5563;
                }

                .close-button {
                    color: #6b7280;

                    &:hover {
                        background-color: #e5e7eb;
                        color: #4b5563;
                    }
                }
            }

            .action-item {
                color: #4b5563;

                svg {
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
    }

    &.dark {
        .action-button {
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);

            &__main {
                color: white;

                &:hover {
                    background: rgba(255, 255, 255, 0.1);
                    color: white;
                }

                &:active {
                    background: rgba(255, 255, 255, 0.2);
                }
            }

            &__divider {
                background: rgba(255, 255, 255, 0.2);
            }

            &__trigger {
                color: rgba(255, 255, 255, 0.8);

                &:hover {
                    background: rgba(255, 255, 255, 0.1);
                    color: white;
                }

                &:active {
                    background: rgba(255, 255, 255, 0.2);
                }
            }
        }

        .actions-menu {
            background: #1f2937;
            border: 1px solid rgba(255, 255, 255, 0.1);

            .actions-menu__header {
                background: rgba(0, 0, 0, 0.2);
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);

                span {
                    color: rgba(255, 255, 255, 0.8);
                }

                .close-button {
                    color: rgba(255, 255, 255, 0.6);

                    &:hover {
                        background: rgba(255, 255, 255, 0.1);
                        color: white;
                    }
                }
            }

            .action-item {
                color: rgba(255, 255, 255, 0.8);

                svg {
                    color: rgba(255, 255, 255, 0.6);
                }

                &:hover {
                    background: rgba(255, 255, 255, 0.1);
                    color: white;

                    svg {
                        color: white;
                    }
                }

                &--danger {
                    color: #ef4444;

                    svg {
                        color: #ef4444;
                    }

                    &:hover {
                        background: rgba(239, 68, 68, 0.1);
                        color: #f87171;

                        svg {
                            color: #f87171;
                        }
                    }
                }
            }
        }
    }
}

.action-button {
    display: flex;
    align-items: center;
    border-radius: 6px;
    overflow: hidden;
    width: 100%;

    &__main {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: none;
        border: none;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
        flex: 1;
    }

    &__content {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    &__divider {
        width: 1px;
        height: 20px;
        flex-shrink: 0;
    }

    &__trigger {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 6px 8px;
        background: none;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        width: 32px;
        flex-shrink: 0;

        svg {
            width: 16px;
            height: 16px;
            transition: transform 0.2s ease;
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
    border-radius: 8px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    z-index: 3;
    margin-top: 4px;
    overflow: hidden;

    &__header {
        padding: 8px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;

        span {
            font-size: 13px;
            font-weight: 600;
        }

        .close-button {
            background: none;
            border: none;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 16px;
            line-height: 1;
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
        font-size: 13px;
        cursor: pointer;
        border-radius: 6px;
        transition: all 0.2s ease;

        svg {
            width: 16px;
            height: 16px;
        }
    }
}
</style> 
