<script setup>
import { inject, ref, computed, watch } from 'vue';
import ScoreFields from './ScoreFields.vue';
import ScreenState from './ScreenState.vue';
import { useResource } from '../use-resource';
const props = defineProps({ plan: Object }), emit = defineEmits(['changed', 'busy']);
const { state, auth } = inject('session');
const manager = computed(() => state.user?.role === 'administrator' && state.user.id !== props.plan.user_id);
const baselines = computed(() => props.plan.assessments.filter(a => a.type === 'baseline'));
const finals = ref([]), busy = ref(false), error = ref(''), errors = ref({}), confirmed = ref(false), notice = ref('');
const editing = ref(null), score = ref(''), note = ref('');
const choice = ref(''), newScore = ref(''), newNote = ref(''), skillPage = ref(1);
const catalogue = useResource(() => '/skills?active=1&per_page=20&page=' + skillPage.value, path => manager.value && props.plan.status === 'draft' ? auth.request(path) : Promise.resolve({data: []}));
const canComplete = computed(() => props.plan.status === 'active' && baselines.value.length && props.plan.weekly_entries.every(w => w.status === 'closed'));
watch(() => props.plan.id + ':' + props.plan.status, () => {
    finals.value = baselines.value.map(a => ({ skill_id: a.skill_id, name: a.skill?.name, is_active: a.skill?.is_active, score: '', note: '' }));
    confirmed.value = false;
}, { immediate: true });
async function mutate(path, payload, method = 'POST') {
    if (busy.value) return;
    if ((path === '/activate' || path === '/complete') && !confirmed.value) return;
    busy.value = true; emit('busy', true); error.value = ''; errors.value = {}; notice.value = '';
    try {
        const result = await auth.request('/plans/' + props.plan.id + path, { method, body: JSON.stringify(payload) });
        // Update local authoritative response immediately; never re-enable a completed write on a failed reload.
        if (path === '/activate' || path === '/complete') emit('changed', { transition: result.data });
        else emit('changed', { assessment: result.data });
        editing.value = null; choice.value = ''; newScore.value = ''; newNote.value = ''; confirmed.value = false; notice.value = 'Saved successfully.';
    } catch (e) { errors.value = e.details || {}; error.value = e.message + ' Your entries are retained. If the request timed out, refresh the plan before retrying.'; }
    finally { busy.value = false; emit('busy', false); }
}
function edit(row) { editing.value = row.id; score.value = row.score; note.value = row.note || ''; errors.value = {}; }
</script>
<template>
<section class="week-card">
<h2>{{ plan.status === 'draft' ? 'Baseline and activation' : plan.status === 'active' ? 'Final assessment' : 'Assessment complete' }}</h2>
<p v-if="notice" class="notice" role="status">{{ notice }}</p><p v-if="error" class="error-box" role="alert">{{ error }}</p>
<p v-if="!manager" class="muted">Assessments are recorded by another administrator. Your view is read-only.</p>
<fieldset :disabled="busy">
<template v-if="plan.status === 'draft'">
<p class="muted">Review every baseline before activating. Activation freezes baseline scores and cannot be reversed.</p>
<div v-for="row in baselines" :key="row.id" class="baseline-review">
<strong>{{ row.skill?.name }}</strong><span>{{ row.score }}</span><span>{{ row.note }}</span>
<button v-if="manager && editing !== row.id" type="button" class="secondary" @click="edit(row)">Correct baseline</button>
<form v-if="manager && editing === row.id" @submit.prevent="mutate('/assessments/' + row.id, { score, note }, 'PATCH')" novalidate>
<label :for="'correct-score-' + row.id">Baseline score</label><input :id="'correct-score-' + row.id" v-model="score" type="number" min="0" max="100" step="0.01"><small class="field-error">{{ errors.score?.[0] }}</small>
<label :for="'correct-note-' + row.id">Note</label><input :id="'correct-note-' + row.id" v-model="note"><small class="field-error">{{ errors.note?.[0] }}</small>
<button class="secondary" type="submit">Save correction</button><button class="text-button" type="button" @click="editing = null">Cancel</button>
</form>
</div>
<template v-if="manager">
<details><summary>Add another baseline skill</summary>
<ScreenState :loading="catalogue.loading.value" :error="catalogue.error.value" :empty="!catalogue.data.value?.data?.length" title="No active skills" @retry="catalogue.load">
<form @submit.prevent="mutate('/assessments', { type: 'baseline', skill_id: choice, score: newScore, note: newNote })" novalidate>
<label for="baseline-skill">Skill</label><select id="baseline-skill" v-model="choice"><option value="" disabled>Select a skill</option><option v-for="skill in catalogue.data.value?.data?.filter(s => !baselines.some(a => a.skill_id === s.id))" :key="skill.id" :value="skill.id">{{ skill.name }}</option></select><small class="field-error">{{ errors.skill_id?.[0] }}</small>
<label for="baseline-score">Baseline score</label><input id="baseline-score" v-model="newScore" type="number" min="0" max="100" step="0.01"><small class="field-error">{{ errors.score?.[0] }}</small>
<label for="baseline-note">Note</label><input id="baseline-note" v-model="newNote"><small class="field-error">{{ errors.note?.[0] }}</small><button class="secondary" type="submit">Add baseline</button>
</form></ScreenState>
<div class="form-actions"><button type="button" class="secondary" :disabled="skillPage === 1" @click="skillPage--">Previous skills</button><button type="button" class="secondary" :disabled="!catalogue.data.value?.links?.next" @click="skillPage++">More skills</button></div>
</details>
<label class="confirm-line"><input type="checkbox" v-model="confirmed">I have reviewed all baseline scores and understand they will be frozen.</label>
<button class="primary" type="button" :disabled="busy || !confirmed || !baselines.length || editing !== null" @click="mutate('/activate', {})">Activate plan</button>
</template>
</template>
<template v-else-if="plan.status === 'active'">
<p v-if="!canComplete" class="notice">Close all open weeks before submitting final scores.</p>
<form v-else-if="manager" @submit.prevent="mutate('/complete', { finals: finals.map(({skill_id, score, note}) => ({skill_id, score, note})) })" novalidate>
<p class="muted">Submit one final score for every baseline skill, including retired skills. The full set is saved in one transaction.</p>
<ScoreFields :rows="finals" :errors="errors" prefix="finals" /><small class="field-error">{{ errors.finals?.[0] }}</small>
<label class="confirm-line"><input type="checkbox" v-model="confirmed">I confirm these final scores. Completing this plan is irreversible.</label>
<button class="primary" type="submit" :disabled="busy || !confirmed">{{ busy ? 'Completing…' : 'Save finals and complete plan' }}</button>
</form>
</template>
<p v-else class="notice">This plan and its recorded assessments are immutable. Review the comparison below.</p>
</fieldset>
</section>
</template>
