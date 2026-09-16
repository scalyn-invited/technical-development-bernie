<script setup>
import { inject } from 'vue';
import ScreenState from './ScreenState.vue';
import { useResource } from '../use-resource';
const props = defineProps({ planId: Number, version: String });
const { auth } = inject('session');
const { data, loading, error, load } = useResource(() => '/plans/' + props.planId + '/comparison' + (props.version ? '?state=' + props.version : ''), auth.request);
</script>
<template>
<section class="week-card"><div class="card-heading"><h2>Baseline to final</h2><button class="secondary" :disabled="loading" @click="load">Refresh comparison</button></div>
<ScreenState :loading="loading" :error="error" :empty="!data?.data?.length" title="No baseline scores yet" description="Your comparison will appear once baseline assessments are recorded." @retry="load">
<p class="notice">Average movement: <strong>{{ data?.summary?.average_movement ?? 'Not available yet' }}</strong> · {{ data?.summary?.compared_skills }} compared · {{ data?.summary?.pending_skills }} awaiting finals</p>
<div class="table-scroll"><table><thead><tr><th>Skill</th><th>Baseline</th><th>Final</th><th>Movement</th></tr></thead><tbody><tr v-for="row in data?.data" :key="row.skill_id"><td>{{ row.skill_name }}<small v-if="!row.is_active">Retired · historical result retained</small></td><td>{{ row.baseline_score }}</td><td>{{ row.final_score ?? 'Pending' }}</td><td>{{ row.delta ?? '—' }}</td></tr></tbody></table></div>
<p class="muted">Movement is final minus baseline, in score points. Pending finals are not treated as zero.</p>
</ScreenState></section>
</template>
