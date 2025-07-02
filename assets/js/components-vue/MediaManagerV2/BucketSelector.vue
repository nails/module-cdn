<template>
    <div class="bucket-selector">
        <div class="bucket-selector__selected" @click="toggleDropdown">
            <span v-if="selectedBuckets.length === 0" class="placeholder">{{ placeholder }}</span>
            <span v-else-if="singleSelect" class="selected-label">
                {{ getSelectedBucketLabel() }}
            </span>
            <span v-else class="selected-count">{{ selectedBuckets.length }} selected</span>
            <span class="dropdown-arrow">▼</span>
        </div>
        <div class="bucket-selector__dropdown" v-if="isOpen">
            <div class="search-box" v-if="buckets.length > 5">
                <input
                    type="text"
                    v-model="searchQuery"
                    placeholder="Search buckets..."
                    @click.stop
                    ref="searchInput"
                />
            </div>
            <div class="options-list">
                <div
                    v-for="bucket in filteredBuckets"
                    :key="bucket.id"
                    class="option-wrapper"
                >
                    <label
                        class="option"
                        @click.stop
                    >
                        <input
                            type="checkbox"
                            :value="bucket.id"
                            :checked="isSelected(bucket.id)"
                            @change="toggleBucket(bucket.id)"
                        />
                        <div class="option-content">
                            <span class="option-label">{{ bucket.label }}</span>
                            <span v-if="bucket.object_count" class="option-sublabel">
                                {{ bucket.object_count }}
                            </span>
                        </div>
                    </label>
                    <div class="option-actions" v-if="showActions && bucketActions.length > 0">
                        <button 
                            v-for="(action, actionIndex) in bucketActions" 
                            :key="actionIndex"
                            v-if="action.condition ? action.condition(bucket) : true"
                            class="option-action-button"
                            :class="action.class"
                            @click.stop="handleBucketAction(action, bucket)"
                            :title="action.title"
                        >
                            <span v-if="action.icon" v-html="action.icon"></span>
                            <span v-else>{{ action.label }}</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="actions" v-if="!singleSelect">
                <button @click.stop="selectAll" class="btn-select-all">Select All</button>
                <button @click.stop="deselectAll" class="btn-clear">Clear</button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'BucketSelector',
    props: {
        value: {
            type: [Array, Number, String],
            default: () => []
        },
        buckets: {
            type: Array,
            required: true
        },
        placeholder: {
            type: String,
            default: 'Select Bucket'
        },
        singleSelect: {
            type: Boolean,
            default: false
        },
        showActions: {
            type: Boolean,
            default: false
        },
        bucketActions: {
            type: Array,
            default: () => []
        }
    },
    data() {
        return {
            selectedBuckets: [],
            isOpen: false,
            searchQuery: '',
        };
    },
    computed: {
        filteredBuckets() {
            if (!this.searchQuery) {
                return this.buckets;
            }

            const query = this.searchQuery.toLowerCase();
            return this.buckets.filter(bucket => {
                const label = bucket.label.toLowerCase();
                return label.includes(query);
            });
        }
    },
    created() {
        this.initializeSelectedBuckets();
    },
    mounted() {
        document.addEventListener('keydown', this.handleKeydown);
    },
    beforeDestroy() {
        document.removeEventListener('click', this.closeDropdown);
        document.removeEventListener('keydown', this.handleKeydown);
    },
    watch: {
        value: {
            handler(newVal) {
                this.initializeSelectedBuckets();
            },
            deep: true
        },
        buckets: {
            handler() {
                this.initializeSelectedBuckets();
            },
            deep: true
        }
    },
    methods: {
        initializeSelectedBuckets() {
            if (this.singleSelect) {
                this.selectedBuckets = this.value ? [this.value] : [];
            } else {
                this.selectedBuckets = Array.isArray(this.value) ? [...this.value] : [];
            }
        },
        toggleDropdown(event) {
            if (event) {
                event.stopPropagation();
            }

            const wasOpen = this.isOpen;
            this.isOpen = !wasOpen;
            this.$emit('dropdown-toggled', this.isOpen);

            if (this.isOpen) {
                setTimeout(() => {
                    document.addEventListener('click', this.closeDropdown);
                }, 0);

                this.$nextTick(() => {
                    this.positionDropdown();
                    if (this.buckets.length > 5) {
                        this.$refs.searchInput?.focus();
                    }
                });
            } else {
                document.removeEventListener('click', this.closeDropdown);
            }
        },
        closeDropdown(event) {
            if (this.isOpen && event) {
                const isOutside = !this.$el.contains(event.target);
                if (isOutside) {
                    this.isOpen = false;
                    this.$emit('dropdown-toggled', false);
                    document.removeEventListener('click', this.closeDropdown);
                }
            } else if (this.isOpen) {
                this.isOpen = false;
                this.$emit('dropdown-toggled', false);
                document.removeEventListener('click', this.closeDropdown);
            }
        },
        toggleBucket(bucketId) {
            if (this.singleSelect) {
                this.selectedBuckets = [bucketId];
                this.$emit('input', bucketId);
                this.$emit('change', bucketId);
                this.closeDropdown();
            } else {
                const index = this.selectedBuckets.indexOf(bucketId);
                if (index > -1) {
                    this.selectedBuckets.splice(index, 1);
                } else {
                    this.selectedBuckets.push(bucketId);
                }
                this.$emit('input', this.selectedBuckets);
                this.$emit('change', this.selectedBuckets);
            }
        },
        isSelected(bucketId) {
            return this.selectedBuckets.includes(bucketId);
        },
        selectAll() {
            this.selectedBuckets = this.buckets.map(bucket => bucket.id);
            this.$emit('input', this.selectedBuckets);
            this.$emit('change', this.selectedBuckets);
        },
        deselectAll() {
            this.selectedBuckets = [];
            this.$emit('input', this.selectedBuckets);
            this.$emit('change', this.selectedBuckets);
        },
        getSelectedBucketLabel() {
            const selectedBucket = this.buckets.find(bucket => bucket.id === this.selectedBuckets[0]);
            return selectedBucket ? selectedBucket.label : '';
        },
        handleBucketAction(action, bucket) {
            this.$emit('bucket-action', { action, bucket });
        },
        handleKeydown(event) {
            if (event.key === 'Escape' && this.isOpen) {
                this.closeDropdown();
            }
        },
        positionDropdown() {
            const trigger = this.$el.querySelector('.bucket-selector__selected');
            const dropdown = this.$el.querySelector('.bucket-selector__dropdown');
            
            if (!trigger || !dropdown) return;
            
            const rect = trigger.getBoundingClientRect();
            const dropdownHeight = 300; // max-height of dropdown
            const spaceBelow = window.innerHeight - rect.bottom;
            const spaceAbove = rect.top;
            
            // Set width to match trigger
            dropdown.style.width = `${rect.width}px`;
            
            // Position horizontally
            dropdown.style.left = `${rect.left}px`;
            
            // Position vertically - prefer below, but above if not enough space
            if (spaceBelow >= dropdownHeight || spaceBelow > spaceAbove) {
                dropdown.style.top = `${rect.bottom + 4}px`;
            } else {
                dropdown.style.top = `${rect.top - dropdownHeight - 4}px`;
            }
        }
    }
}
</script>

<style lang="scss" scoped>
.bucket-selector {
    position: relative;
    width: 100%;

    &__selected {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background: white;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s ease;

        &:hover {
            border-color: #9ca3af;
        }

        .placeholder {
            color: #9ca3af;
        }

        .selected-label {
            color: #111827;
            font-weight: 500;
        }

        .selected-count {
            color: #4f46e5;
            font-weight: 500;
        }

        .dropdown-arrow {
            color: #6b7280;
            font-size: 12px;
            transition: transform 0.2s ease;
        }
    }

    &__dropdown {
        position: fixed;
        width: 100%;
        max-height: 300px;
        background: #ffffff;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        margin-top: 4px;
        z-index: 100000;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
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
            padding: 8px 8px 4px 8px;
            border-bottom: 1px solid #e5e7eb;
            flex-shrink: 0;

            input {
                width: 100%;
                padding: 6px 8px;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                font-size: 14px;
                margin: 0 0 4px 0;

                &:focus {
                    outline: none;
                    border-color: #4f46e5;
                    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
                }
            }
        }

        .options-list {
            max-height: 200px;
            overflow-y: auto;
            padding: 4px 0;
            flex-grow: 1;

            .option-wrapper {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 6px 12px;
                cursor: pointer;
                transition: background-color 0.2s ease;

                &:hover {
                    background: #f9fafb;
                }

                .option {
                    display: flex;
                    align-items: flex-start;
                    flex-grow: 1;
                    margin: 0;
                    padding: 0;

                    input {
                        margin-right: 8px;
                        margin-top: 5px;
                        margin-bottom: 0;
                        flex-shrink: 0;
                        align-self: flex-start;
                    }

                    .option-content {
                        display: flex;
                        flex-direction: column;
                        flex-grow: 1;
                        min-width: 0;
                    }

                    .option-label {
                        color: #111827;
                        font-size: 14px;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }

                    .option-sublabel {
                        color: #6b7280;
                        font-size: 12px;
                        margin-top: 2px;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }
                }

                .option-actions {
                    display: flex;
                    align-items: center;
                    gap: 4px;
                    margin-left: 8px;

                    .option-action-button {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        width: 24px;
                        height: 24px;
                        border: none;
                        background: none;
                        border-radius: 4px;
                        padding: 0;
                        cursor: pointer;
                        color: #6b7280;
                        transition: all 0.2s ease;

                        &:hover {
                            background-color: #e5e7eb;
                            color: #4b5563;
                        }

                        &.delete-action {
                            color: white;
                            background-color: #ef4444;

                            &:hover {
                                background-color: #dc2626;
                            }
                        }

                        svg {
                            width: 14px;
                            height: 14px;
                        }
                    }
                }
            }
        }

                .actions {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            padding: 8px;
            border-top: 1px solid #e5e7eb;
            flex-shrink: 0;

            button {
                flex: 1;
                padding: 6px 12px;
                border: none;
                border-radius: 4px;
                background: #f3f4f6;
                cursor: pointer;
                font-size: 12px;
                color: #4b5563;
                transition: all 0.2s ease;

                &:hover {
                    background: #e5e7eb;
                }

                &.btn-select-all {
                    background: #eef2ff;
                    color: #4f46e5;

                    &:hover {
                        background: #e0e7ff;
                    }
                }
            }
        }
    }
}
</style>
