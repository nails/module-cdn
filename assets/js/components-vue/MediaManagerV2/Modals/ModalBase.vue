<template>
    <div 
        v-if="visible"
        class="base-modal-overlay"
        @click.self="handleOverlayClick"
        @keydown.esc="handleEscapeKey"
        tabindex="-1"
        ref="modalOverlay"
    >
        <div class="base-modal-container" :class="containerClass">
            <div class="base-modal-header">
                <slot name="header">
                    <h3 v-if="title">{{ title }}</h3>
                </slot>
                <button 
                    v-if="showClose" 
                    class="base-modal-close-button" 
                    @click="handleClose"
                    aria-label="Close modal"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
            <div class="base-modal-body">
                <slot></slot>
            </div>
            <div v-if="$slots.footer" class="base-modal-footer">
                <slot name="footer"></slot>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ModalBase',
    props: {
        visible: {
            type: Boolean,
            default: false
        },
        title: {
            type: String,
            default: ''
        },
        showClose: {
            type: Boolean,
            default: true
        },
        containerClass: {
            type: String,
            default: ''
        },
        closeOnOverlayClick: {
            type: Boolean,
            default: true
        },
        closeOnEscape: {
            type: Boolean,
            default: true
        }
    },
    watch: {
        visible(newVal) {
            if (newVal) {
                this.$nextTick(() => {
                    this.focusModal();
                });
            }
        }
    },
    mounted() {
        this.focusModal();
    },
    methods: {
        handleOverlayClick() {
            if (this.closeOnOverlayClick) {
                this.handleClose();
            }
        },
        handleClose() {
            this.$emit('close');
        },
        handleEscapeKey() {
            if (this.closeOnEscape) {
                this.handleClose();
            }
        },
        focusModal() {
            if (this.$refs.modalOverlay) {
                this.$refs.modalOverlay.focus();
            }
        }
    }
}
</script>

<style scoped>
.base-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100001;
    padding: 20px;
    outline: none;
}

.base-modal-container {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    max-width: 500px;
    max-height: 90vh;
    width: 100%;
    display: flex;
    flex-direction: column;
    overflow: visible;
}

.base-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
    min-height: 48px;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
}

.base-modal-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #4b5563;
}

.base-modal-close-button {
    background: none;
    border: none;
    padding: 4px;
    cursor: pointer;
    color: #6b7280;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.base-modal-close-button:hover {
    background: #e5e7eb;
    color: #4b5563;
}

.base-modal-close-button svg {
    width: 16px;
    height: 16px;
}

.base-modal-body {
    padding: 16px;
    max-height: calc(100vh - 200px);
    overflow-y: auto;
    overflow-x: visible; /* Allow dropdowns to extend beyond the modal body */
    flex: 1;
}

.base-modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    padding: 12px 16px;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
    border-bottom-left-radius: 8px;
    border-bottom-right-radius: 8px;
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .base-modal-overlay {
        padding: 10px;
    }
    
    .base-modal-container {
        max-width: 95vw;
        max-height: 95vh;
    }
    
    .base-modal-header {
        padding: 16px 20px 0 20px;
    }
    
    .base-modal-body {
        padding: 20px;
    }
    
    .base-modal-footer {
        padding: 0 20px 20px 20px;
        flex-direction: column;
        align-items: stretch;
    }
    
    .base-modal-footer > * {
        width: 100%;
    }
}
</style> 
