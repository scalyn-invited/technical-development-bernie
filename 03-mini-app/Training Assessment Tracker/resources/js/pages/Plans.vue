<script setup>
import { inject, ref } from 'vue';
import WorkspaceShell from '../components/WorkspaceShell.vue';
import ScreenState from '../components/ScreenState.vue';
import Pagination from '../components/Pagination.vue';
import { useResource } from '../use-resource';
const { state, auth } = inject('session');
const status = ref(''), page = ref(1);
const { data, loading, error, load } = useResource(() => '/plans?page=' + page.value + '&per_page=10' + (status.value ? '&status=' + status.value : ''), auth.request);
function filter(event) { page.value = 1; status.value = event.target.value; }
</script>
<template>
    <WorkspaceShell>
        <RouterLink v-if="state.user?.role === 'administrator'" to="/plans/new" class="secondary create-plan-link">Create development plan ↗</RouterLink>
        <div class="page-heading"><div><span class="section-number">BUILD WITH INTENTION</span><h1>Development plans<span class="green">.</span></h1><p class="muted">A clear starting point. A focused path forward.</p></div><span class="pill">YOUR PROGRAMME</span></div>
        <section class="white-card screen-card">
            <div class="toolbar"><div><h2>Plan directory</h2><p class="muted">Follow each member’s development journey.</p></div><div class="filter"><label for="plan-status">Status</label><select id="plan-status" :value="status" @change="filter"><option value="">All statuses</option><option value="draft">Draft</option><option value="active">Active</option><option value="completed">Completed</option></select></div></div>
            <ScreenState :loading="loading" :error="error" :empty="!data?.data?.length" title="No plans found" description="Try another status, or ask your administrator to assign a plan." @retry="load">
                <div class="table-scroll"><table><thead><tr><th>Member</th><th>Status</th><th>Skills</th><th>Current week</th><th><span class="sr-only">Action</span></th></tr></thead><tbody><tr v-for="plan in data?.data" :key="plan.id"><td><strong>{{ plan.member?.name }}</strong><small>Plan #{{ plan.id }}</small></td><td><span class="status-tag" :class="plan.status">{{ plan.status }}</span></td><td>{{ plan.skills_count }}</td><td>{{ plan.current_week ? 'Week ' + plan.current_week : plan.weekly_entries_count ? 'All weeks closed' : 'Not started' }}</td><td><RouterLink :to="'/plans/' + plan.id" class="table-link" :aria-label="'Open plan for ' + plan.member?.name">View plan ↗</RouterLink></td></tr></tbody></table></div>
            </ScreenState>
            <Pagination :meta="data?.meta" :busy="loading" @page="page = $event" />
        </section>
    </WorkspaceShell>
</template>
