<template>
    <div class="actions-dropdown" :class="theme" ref="dropdown">
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

        <!-- Use the ActionsMenu component -->
        <actions-menu
            :is-open="isOpen"
            :theme="theme"
            :has-callback="hasCallback"
            :trigger-element="$refs.dropdown"
            @close="isOpen = false"
            @action="handleAction"
        />
    </div>
</template>

<script>
import ActionsMenu from './ActionsMenu.vue'

export default {
    name: 'ObjectActions',
    components: {
        ActionsMenu
    },
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
    methods: {
        toggleDropdown(event) {
            event.stopPropagation();
            this.isOpen = !this.isOpen;
        },
        handleAction(action) {
            this.$emit('action', action);
            this.isOpen = false;
        }
    }
}
</script>

<style lang="scss" scoped>
.actions-dropdown {
    position: relative;
    margin-top: 8px;
    z-index: 1; /* Ensure it creates a stacking context */

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
</style> 
