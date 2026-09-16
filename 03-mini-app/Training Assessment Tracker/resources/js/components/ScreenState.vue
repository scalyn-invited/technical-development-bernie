<script setup>
defineProps({ loading: Boolean, error: String, empty: Boolean, title: String, description: String });
defineEmits(['retry']);
</script>
<template>
    <div v-if="loading" class="screen-state" role="status" aria-live="polite"><span class="loading-ring"></span><h2>Loading…</h2><p>Bringing your latest information into focus.</p></div>
    <div v-else-if="error" class="screen-state state-error" role="alert"><span class="state-symbol">!</span><h2>We couldn’t load this screen.</h2><p>{{ error }}</p><button class="primary" @click="$emit('retry')">Try again</button></div>
    <div v-else-if="empty" class="screen-state"><span class="state-symbol">○</span><h2>{{ title || 'Nothing here yet' }}</h2><p>{{ description || 'Your information will appear here when it is available.' }}</p></div>
    <slot v-else />
</template>
