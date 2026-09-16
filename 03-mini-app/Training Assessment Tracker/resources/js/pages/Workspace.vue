<script setup>
import { inject, ref, computed, onMounted } from 'vue';
import WorkspaceShell from '../components/WorkspaceShell.vue';
const { state, auth } = inject('session');


const loading = ref(true);
const error = ref('');
const profile = ref(null);
const firstName = computed(() => (state.user?.name || 'there').split(' ')[0]);
async function refresh() {
    loading.value = true; error.value = '';
    try { profile.value = (await auth.request('/me')).data; state.user = profile.value; }
    catch (e) { error.value = e.message; }
    finally { loading.value = false; }
}

onMounted(refresh);
</script>
<template>
<WorkspaceShell>
                <div class="page-heading"><div><span class="section-number">YOUR DEVELOPMENT SPACE</span><h1>Welcome back, {{ firstName }}<span class="green">.</span></h1><p class="muted">A little focus today. A meaningful step forward.</p></div><span class="pill"><span class="tiny-dot"></span> SIGNED IN</span></div>
                <div v-if="state.notice" class="notice" role="status">{{ state.notice }}</div>
                <div v-if="error" class="error-box" role="alert">{{ error }} <button class="text-button" @click="refresh">Try again</button></div>
                <section class="welcome-banner"><div><span class="eyebrow">THE BIG PICTURE</span><h2>Your next chapter<br>starts with a baseline.</h2><p>Understand your starting point, practise with purpose,<br class="desktop-break"> and make your progress visible.</p></div><div class="banner-orbit" aria-hidden="true"><span>↗</span></div></section>
                <div class="overview-grid">
                    <section class="white-card"><div class="card-heading"><h2>Your account</h2><button class="text-button" @click="refresh" :disabled="loading">{{ loading ? 'Checking…' : 'Refresh ↻' }}</button></div>
                        <p v-if="loading" class="muted" role="status">Loading your account…</p>
                        <dl v-else-if="profile" class="profile-list"><div><dt>Name</dt><dd>{{ profile.name }}</dd></div><div><dt>Email</dt><dd>{{ profile.email }}</dd></div><div><dt>Role</dt><dd><span class="role-tag">{{ profile.role }}</span></dd></div><div><dt>Development plan</dt><dd>{{ profile.development_plan_status || 'Not assigned yet' }}</dd></div></dl>
                    </section>
                    <section class="white-card journey"><div class="card-heading"><h2>A focused journey</h2><span class="muted">01 — 03</span></div><ol><li><span>01</span><div><h3>Know your starting point</h3><p>Capture a baseline for your chosen skills.</p></div></li><li><span>02</span><div><h3>Put learning into practice</h3><p>Turn weekly objectives into evidence.</p></div></li><li><span>03</span><div><h3>Reflect on your growth</h3><p>Compare final scores with your baseline.</p></div></li></ol></section>
                </div>
                <footer class="workspace-footer"><span>Small steps add up.</span><span>Scalyn OPC · Development programme</span></footer>
</WorkspaceShell>
</template>
