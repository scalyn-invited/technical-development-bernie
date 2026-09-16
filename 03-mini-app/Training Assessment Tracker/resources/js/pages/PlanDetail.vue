<script setup>
import { inject, computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import WorkspaceShell from '../components/WorkspaceShell.vue';
import ScreenState from '../components/ScreenState.vue';
import WeekEditor from '../components/WeekEditor.vue';
import NewWeekForm from '../components/NewWeekForm.vue';
import PlanLifecycle from '../components/PlanLifecycle.vue';
import PlanComparison from '../components/PlanComparison.vue';
import { useResource } from '../use-resource';
const { state, auth } = inject('session');
const route = useRoute();
const { data, loading, error, load } = useResource(() => '/plans/' + route.params.plan, auth.request);
const plan = computed(() => data.value?.data);
const editable = computed(() => state.user?.role === 'administrator' && state.user.id !== plan.value?.user_id && plan.value?.status === 'active');
const skills = computed(() => (plan.value?.assessments || []).filter(a => a.type === 'baseline' && a.skill?.is_active).map(a => a.skill));
const canCreate = computed(() => editable.value && skills.value.length && plan.value.weekly_entries.every(w => w.status === 'closed'));
const message = ref('');
const lifecycleBusy = ref(false), revision = ref(0);
function assessmentChanged(result) {
    if (result.transition) Object.assign(plan.value, result.transition);
    if (result.assessment) {
        const index = plan.value.assessments.findIndex(a => a.id === result.assessment.id);
        if (index < 0) plan.value.assessments.push(result.assessment);
        else plan.value.assessments[index] = result.assessment;
    }
    revision.value++;
}
function saved(week) {
    const index = plan.value.weekly_entries.findIndex(w => w.id === week.id);
    if (index < 0) { plan.value.weekly_entries.push(week); message.value = 'Week ' + week.week_number + ' created.'; }
    else plan.value.weekly_entries[index] = week;
}
</script>
<template>
    <WorkspaceShell>
        <RouterLink to="/plans" class="back-link">← Development plans</RouterLink>
        <ScreenState :loading="loading" :error="error" :empty="!plan" title="No plan available" description="Choose a plan from the directory to review its progress." @retry="load">
            <template v-if="plan">
                <div class="page-heading"><div><span class="section-number">PLAN #{{ plan.id }}</span><h1>{{ plan.member?.name }}<span class="green">.</span></h1><p class="muted">{{ plan.weekly_focus }}</p></div><span class="status-tag" :class="plan.status">{{ plan.status }}</span></div>
                <section class="plan-summary"><div><span class="section-number">AREAS TO DEVELOP</span><p>{{ plan.key_gaps }}</p></div><div><span class="section-number">BASELINE SKILLS</span><div class="skill-chips"><span v-for="assessment in plan.assessments.filter(a => a.type === 'baseline')" :key="assessment.id">{{ assessment.skill?.name }} <b>{{ assessment.score }}</b></span><span v-if="!plan.assessments.some(a => a.type === 'baseline')">No baselines recorded</span></div></div></section>
                <PlanLifecycle :key="plan.id" :plan="plan" @changed="assessmentChanged" @busy="lifecycleBusy = $event" />
                <div class="section-heading"><div><h2>Weekly progress</h2><p class="muted">Objectives become evidence. Evidence becomes progress.</p></div><button class="secondary" :disabled="lifecycleBusy" @click="load">Refresh plan</button></div>
                <fieldset class="progress-fieldset" :disabled="lifecycleBusy">
                <p v-if="message" class="notice" role="status">{{ message }}</p>
                <p v-if="!editable" class="notice">{{ plan.status === 'draft' ? 'This plan must be activated before weekly progress can be recorded.' : plan.status === 'completed' ? 'This completed plan is read-only.' : 'You have read-only access to this plan.' }}</p>
                <ScreenState :empty="!plan.weekly_entries.length" title="A fresh page for progress" description="No weeks have been planned yet. An administrator can add the first objective once the plan is active.">
                    <WeekEditor v-for="week in plan.weekly_entries" :key="week.id" :week="week" :plan-id="plan.id" :editable="editable" @saved="saved" />
                </ScreenState>
                <NewWeekForm v-if="canCreate" :plan="plan" :skills="skills" @saved="saved" />
                <p v-else-if="editable" class="muted next-week-note">{{ !skills.length ? 'An active baseline skill is needed to plan another week.' : 'Close the current week before planning the next one.' }}</p>
                </fieldset>
                <PlanComparison :plan-id="plan.id" :version="plan.status + '-' + revision" />
            </template>
        </ScreenState>
    </WorkspaceShell>
</template>
