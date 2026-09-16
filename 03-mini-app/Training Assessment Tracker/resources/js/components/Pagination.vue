<script setup>
defineProps({ meta: Object, busy: Boolean });
defineEmits(['page']);
</script>
<template>
    <nav v-if="meta && meta.last_page > 1" class="pagination" aria-label="Pagination">
        <span>Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} results</span>
        <div><button class="secondary" :disabled="busy || meta.current_page <= 1" @click="$emit('page', meta.current_page - 1)">← Previous</button><button class="secondary" :disabled="busy || meta.current_page >= meta.last_page" @click="$emit('page', meta.current_page + 1)">Next →</button></div>
    </nav>
</template>
