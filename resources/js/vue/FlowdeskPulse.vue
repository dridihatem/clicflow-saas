<script setup>
defineProps({
    appName: {
        type: String,
        default: 'Flowdesk',
    },
    message: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div
        class="rounded-xl border border-[var(--flow-border)] bg-[var(--flow-surface-muted)] px-4 py-3 text-sm text-[var(--flow-text-muted)]"
    >
        <span class="font-medium text-[var(--flow-text)]">{{ appName }}</span>
        <template v-if="message">
            —
            <span>{{ message }}</span>
        </template>
    </div>
</template>
