<script setup>
import { inject } from 'vue';
const { state } = inject('session');
</script>
<template>
    <div v-if="state.checking" class="boot" role="status"><span class="brand-mark">s</span><p>Opening your workspace…</p></div>
    <RouterView v-else />
</template>
