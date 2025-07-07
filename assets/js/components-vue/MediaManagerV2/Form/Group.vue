<template>
    <div class="form-group">
        <label
            v-if="label"
            :for="this.for"
            class="form-group__label"
        >
            {{ label }}
        </label>
        <div v-if="subLabel" class="form-group__sublabel">
            {{ subLabel }}
        </div>
        <slot />
    </div>
</template>
<script>
export default {
    name: 'FormGroup',
    props: {
        label: {
            type: String,
            default: ''
        },
        for: {
            type: String,
            default: ''
        },
        subLabel: {
            type: String,
            default: ''
        },
    }
}
</script>
<style scoped>
.form-group {
    margin-bottom: 1rem;

    &__label {
        display: block;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #374151;
    }

    &__sublabel {
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
    }

    input[type="text"]:focus {
        outline: none;
        border-color: #4f46e5;
        box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
    }

    input[type="text"]:disabled {
        background: #f3f4f6;
        cursor: not-allowed;
    }
}
</style>
