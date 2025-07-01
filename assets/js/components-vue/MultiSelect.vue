<template>
    <div class="multi-select" :class="{ 'open-upwards': openUpwards }">
        <div class="multi-select__selected" @click="toggleDropdown">
            <span v-if="selectedItems.length === 0" class="placeholder">Select {{ title }}</span>
            <span v-else-if="singleSelect" class="selected-label">
                {{ options.find(opt => opt.id === selectedItems[0])?.label }}
            </span>
            <span v-else class="selected-count">{{ selectedItems.length }} selected</span>
            <span class="dropdown-arrow">▼</span>
        </div>
        <div class="multi-select__dropdown" v-if="isOpen">
            <div class="search-box" v-if="options.length > 10">
                <input
                    type="text"
                    v-model="searchQuery"
                    placeholder="Search..."
                    @click.stop
                    ref="searchInput"
                />
            </div>
            <div class="options-list">
                <div
                    v-for="option in filteredOptions"
                    :key="option.id"
                    class="option-wrapper"
                >
                    <label
                        class="option"
                        @click.stop
                    >
                        <input
                            type="checkbox"
                            :value="option.id"
                            :checked="isSelected(option.id)"
                            @change="toggleOption(option.id)"
                        />
                        <div class="option-content">
                            <span class="option-label">{{ option.label }}</span>
                            <span v-if="subLabelKey && option[subLabelKey]" class="option-sublabel">
                                {{ option[subLabelKey] }}
                            </span>
                        </div>
                    </label>
                    <div class="option-actions" v-if="optionActions.length > 0">
                        <button 
                            v-for="(action, actionIndex) in optionActions" 
                            :key="actionIndex"
                            v-if="action.condition ? action.condition(option) : true"
                            class="option-action-button"
                            :class="action.class"
                            @click.stop="handleOptionAction(action, option)"
                            :title="action.title"
                        >
                            <span v-if="action.icon" v-html="action.icon"></span>
                            <span v-else>{{ action.label }}</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="actions">
                <button v-if="!singleSelect" @click.stop="selectAll" class="btn-select-all">Select All</button>
                <button @click.stop="deselectAll" class="btn-clear" :class="{ 'btn-clear--single': singleSelect }">Clear</button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MultiSelect',
    props: {
        value: {
            type: Array,
            default: () => []
        },
        options: {
            type: Array,
            required: true
        },
        title: {
            type: String,
            default: 'Items'
        },
        subLabelKey: {
            type: String,
            default: null
        },
        singleSelect: {
            type: Boolean,
            default: false
        },
        openUpwards: {
            type: Boolean,
            default: false
        },
        optionActions: {
            type: Array,
            default: () => []
        }
    },
    data() {
        return {
            selectedItems: [],
            isOpen: false,
            searchQuery: '',
        };
    },
    computed: {
        filteredOptions() {
            if (!this.searchQuery) {
                return this.options;
            }

            const query = this.searchQuery.toLowerCase();
            return this.options.filter(option => {
                const label = option.label.toLowerCase();
                const subLabel = this.subLabelKey && option[this.subLabelKey]
                    ? option[this.subLabelKey].toLowerCase()
                    : '';

                return label.includes(query) || subLabel.includes(query);
            });
        }
    },
    created() {
        this.selectedItems = [...this.value];
    },
    mounted() {
        // Add global event listeners
        document.addEventListener('keydown', this.handleKeydown);
        // We'll add the click listener in toggleDropdown instead
    },
    beforeDestroy() {
        // Remove global event listeners
        document.removeEventListener('click', this.closeDropdown);
        document.removeEventListener('keydown', this.handleKeydown);
    },
    watch: {
        value: {
            handler(newVal) {
                this.selectedItems = [...newVal];
            },
            deep: true
        }
    },
    methods: {
        toggleDropdown(event) {
            if (event) {
                event.stopPropagation();
            }

            const wasOpen = this.isOpen;
            this.isOpen = !wasOpen;
            this.$emit('dropdown-toggled', this.isOpen);

            if (this.isOpen) {
                // Add click listener with a slight delay to avoid the current click closing the dropdown
                setTimeout(() => {
                    document.addEventListener('click', this.closeDropdown);
                }, 0);

                // Focus search input when opening dropdown
                if (this.options.length > 10) {
                    this.$nextTick(() => {
                        this.$refs.searchInput?.focus();
                    });
                }
            } else {
                // Remove click listener when closing
                document.removeEventListener('click', this.closeDropdown);
            }
        },
        closeDropdown(event) {
            // Only close if we have a click event and it's outside the dropdown
            if (this.isOpen && event) {
                // Check if the click is outside the multi-select component
                const isOutside = !this.$el.contains(event.target);
                if (isOutside) {
                    this.isOpen = false;
                    this.$emit('dropdown-toggled', false);
                    document.removeEventListener('click', this.closeDropdown);
                }
            } else if (this.isOpen) {
                // If no event is provided, just close the dropdown (for programmatic calls)
                this.isOpen = false;
                this.$emit('dropdown-toggled', false);
                document.removeEventListener('click', this.closeDropdown);
            }
        },
        // Method to be called externally to close the dropdown
        close() {
            if (this.isOpen) {
                this.isOpen = false;
                this.$emit('dropdown-toggled', false);
                document.removeEventListener('click', this.closeDropdown);
            }
        },
        isSelected(id) {
            return this.selectedItems.includes(id);
        },
        toggleOption(id) {
            if (this.singleSelect) {
                this.selectedItems = [id];
                this.close(); // Use close() instead of closeDropdown() to ensure event listener is removed
            } else {
                const index = this.selectedItems.indexOf(id);
                if (index === -1) {
                    // Add to selection
                    this.selectedItems.push(id);
                } else {
                    // Remove from selection
                    this.selectedItems.splice(index, 1);
                }
            }
            this.$emit('input', [...this.selectedItems]);
        },
        selectAll() {
            if (!this.singleSelect) {
                this.selectedItems = this.filteredOptions.map(option => option.id);
                this.$emit('input', [...this.selectedItems]);
            }
            this.close(); // Use close() instead of closeDropdown() to ensure event listener is removed
        },
        deselectAll() {
            this.selectedItems = [];
            this.$emit('input', []);
            this.close(); // Use close() instead of closeDropdown() to ensure event listener is removed
        },
        handleKeydown(event) {
            if (event.key === 'Escape' && this.isOpen) {
                this.close(); // Use close() instead of directly setting isOpen to ensure event listener is removed
            }
        },

        handleOptionAction(action, option) {
            this.$emit('option-action', { action, option });
        }
    }
}
</script>

<style lang="scss" scoped>
.multi-select {
    position: relative;
    width: 100%;

    &.open-upwards {
        .multi-select__dropdown {
            top: auto;
            bottom: 100%;
            margin-top: 0;
            margin-bottom: 4px;
            flex-direction: column-reverse;
            transform-origin: bottom center;
            animation: dropdownSlideUp 0.2s ease-out;
            max-height: 250px;

            .options-list {
                max-height: 150px;
            }
        }

        @keyframes dropdownSlideUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    }

    &__selected {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        border: 1px solid #cccccc;
        border-radius: 4px;
        background: #ffffff;
        cursor: pointer;

        .placeholder {
            color: #999999;
        }

        .dropdown-arrow {
            font-size: 10px;
            color: #666666;
            margin-left: 8px;
        }
    }

    &__dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        width: 100%;
        max-height: 300px;
        background: #ffffff;
        border: 1px solid #cccccc;
        border-radius: 4px;
        margin-top: 4px;
        z-index: 1050; /* Increased z-index to appear above modal */
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        display: flex;
        flex-direction: column;
        animation: dropdownSlideDown 0.2s ease-out;

        @keyframes dropdownSlideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .search-box {
            padding: 8px;
            border-bottom: 1px solid #eeeeee;
            flex-shrink: 0;

            input {
                width: 100%;
                padding: 6px;
                border: 1px solid #dddddd;
                border-radius: 4px;
                margin: 0;

                &:focus {
                    outline: none;
                    border-color: #1a73e8;
                }
            }
        }

        .options-list {
            max-height: 200px;
            overflow-y: auto;
            padding: 8px 0;
            margin: 0;
            flex-grow: 1;

            .option-wrapper {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 6px 12px;
                cursor: pointer;
                margin: 0;
                width: 100%;
                box-sizing: border-box;

                &:hover {
                    background: #f5f5f5;
                }

                .option {
                    display: flex;
                    align-items: flex-start;
                    flex-grow: 1;
                    margin: 0;
                    padding: 0;

                    input {
                        margin-right: 8px;
                        margin-top: 4px;
                        margin-bottom: 0;
                        flex-shrink: 0;
                    }

                    .option-content {
                        display: flex;
                        flex-direction: column;
                        flex-grow: 1;
                        min-width: 0; /* Needed for text-overflow to work */
                    }

                    .option-label {
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                        width: 100%;
                    }

                    .option-sublabel {
                        font-size: 12px;
                        color: #666666;
                        font-style: italic;
                        margin-top: 2px;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                        width: 100%;
                    }
                }

                .option-actions {
                    display: flex;
                    align-items: center;
                    gap: 4px;
                    margin-left: 8px;
                    transition: opacity 0.2s ease;
                }

                /* Only non-delete actions are hidden by default */
                .option-action-button:not(.delete-action) {
                    opacity: 0;
                    transition: opacity 0.2s ease;
                }

                &:hover .option-action-button:not(.delete-action) {
                    opacity: 1;
                }

                .option-action-button {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 28px;
                    height: 28px;
                    border: none;
                    background: none;
                    border-radius: 4px;
                    padding: 0;
                    cursor: pointer;
                    color: #666666;
                    transition: all 0.2s ease;

                    span {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        line-height: 1;
                    }

                    &:hover {
                        background-color: #e5e7eb;
                        color: #4b5563;
                    }

                    &.delete-action {
                        color: white;
                        background-color: #ef4444;
                        border: 1px solid #dc2626;
                        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);

                        &:hover {
                            background-color: #dc2626;
                            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                            transform: translateY(-1px);
                        }

                        &:active {
                            transform: translateY(1px);
                            box-shadow: none;
                        }
                    }

                    svg {
                        width: 16px;
                        height: 16px;
                    }
                }
            }
        }

        .actions {
            display: flex;
            justify-content: space-between;
            padding: 8px;
            border-top: 1px solid #eeeeee;
            flex-shrink: 0;

            button {
                padding: 6px 12px;
                border: none;
                border-radius: 4px;
                background: #f5f5f5;
                cursor: pointer;
                font-size: 12px;
                margin: 0;
                width: calc(50% - 4px);
                box-sizing: border-box;

                &.btn-clear--single {
                    width: 100%;
                }

                &:hover {
                    background: #e5e5e5;
                }

                &.btn-select-all {
                    background: #e8f0fe;
                    color: #1a73e8;

                    &:hover {
                        background: #d2e3fc;
                    }
                }
            }
        }
    }
}
</style>
