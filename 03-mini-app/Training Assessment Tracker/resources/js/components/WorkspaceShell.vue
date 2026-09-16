<script setup>
import { inject, ref, computed } from 'vue';
import { useRouter } from 'vue-router';
const { state, auth } = inject('session');
const router = useRouter();
const busy = ref(false);
const initial = computed(() => state.user?.name?.slice(0, 1) || 'S');
async function logout() {
    if (busy.value) return;
    busy.value = true;
    await auth.logout();
    await router.replace('/login');
    busy.value = false;
}
const links = [{ to: '/workspace', title: 'Overview', icon: '▦' }, { to: '/plans', title: 'Development plans', icon: '▤' }, { to: '/open-weeks', title: 'Open weeks', icon: '◷' }];
</script>
<template>
    <div class="workspace">
        <aside class="sidebar">
            <RouterLink to="/workspace" class="wordmark"><span class="brand-mark">s</span>scalyn</RouterLink>
            <div class="sidebar-title">YOUR WORKSPACE</div>
            <nav aria-label="Main navigation"><RouterLink v-for="link in links" :key="link.to" :to="link.to" class="nav-item" active-class="nav-current"><span aria-hidden="true">{{ link.icon }}</span>{{ link.title }}</RouterLink></nav>
            <div class="sidebar-bottom"><span class="eyebrow">ONE STEP AT A TIME</span><p>Progress starts<br>with showing up.</p><span class="sidebar-arrow" aria-hidden="true">↗</span></div>
        </aside>
        <div class="workspace-main">
            <header class="topbar"><span>Training Assessment Tracker</span><div class="account"><span class="avatar">{{ initial }}</span><span>{{ state.user?.name }}</span><button class="text-button" @click="logout" :disabled="busy">{{ busy ? 'Signing out…' : 'Sign out ↗' }}</button></div></header>
            <nav class="mobile-nav" aria-label="Mobile navigation"><RouterLink v-for="link in links" :key="link.to" :to="link.to" active-class="selected">{{ link.title }}</RouterLink></nav>
            <main class="workspace-content"><slot /></main>
        </div>
    </div>
</template>
