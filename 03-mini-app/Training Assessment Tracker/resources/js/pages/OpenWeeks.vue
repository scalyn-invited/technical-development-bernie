<script setup>
import { inject, ref } from 'vue';
import WorkspaceShell from '../components/WorkspaceShell.vue';
import ScreenState from '../components/ScreenState.vue';
import Pagination from '../components/Pagination.vue';
import { useResource } from '../use-resource';
const { auth } = inject('session');
const page = ref(1), status = ref('');
const { data, loading, error, load } = useResource(() => '/weeks/open?page=' + page.value + '&per_page=10' + (status.value ? '&status=' + status.value : ''), auth.request);
function filter(event) { page.value = 1; status.value = event.target.value; }
</script>
<template>
    <WorkspaceShell>
        <div class="page-heading"><div><span class="section-number">KEEP THE MOMENTUM</span><h1>Open weeks<span class="green">.</span></h1><p class="muted">The next steps awaiting evidence or closure.</p></div><button class="secondary" @click="load" :disabled="loading">Refresh ↻</button></div>
        <section class="white-card screen-card">
            <div class="toolbar"><div><h2>Progress queue</h2><p class="muted">Every open week within your permitted plans.</p></div><div class="filter"><label for="week-status">Show</label><select id="week-status" :value="status" @change="filter"><option value="">All open weeks</option><option value="planned">Awaiting evidence</option><option value="evidenced">Awaiting closure</option></select></div></div>
            <ScreenState :loading="loading" :error="error" :empty="!data?.data?.length" title="You’re all caught up" description="No weeks are awaiting evidence or closure for this filter." @retry="load">
                <div class="queue-list"><article v-for="week in data?.data" :key="week.id" class="queue-row"><span class="week-number">{{ String(week.week_number).padStart(2, '0') }}</span><div class="queue-info"><strong>{{ week.plan?.member?.name }}</strong><p>{{ week.objective }}</p><small>{{ week.skill?.name }} · Plan #{{ week.development_plan_id }}</small></div><span class="status-tag" :class="week.status">{{ week.status === 'planned' ? 'Awaiting evidence' : 'Awaiting closure' }}</span><RouterLink class="table-link" :to="'/plans/' + week.development_plan_id + '#week-' + week.id">Review week ↗</RouterLink></article></div>
            </ScreenState>
            <Pagination :meta="data?.meta" :busy="loading" @page="page = $event" />
        </section>
    </WorkspaceShell>
</template>
