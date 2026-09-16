<script setup>
import { inject, ref, reactive, watch } from 'vue';
const props = defineProps({ week: Object, planId: Number, editable: Boolean });
const emit = defineEmits(['saved']);
const { auth } = inject('session');
const busy = ref(false), error = ref(''), success = ref(''), errors = ref({});
const form = reactive({ objective: '', evidence: '', outcome_score: '' });
watch(() => props.week, week => Object.assign(form, { objective: week.objective, evidence: week.evidence ?? '', outcome_score: week.outcome_score ?? '' }), { immediate: true });
async function save(action) {
    if (busy.value || !props.editable || props.week.status === 'closed') return;
    busy.value = true; error.value = ''; success.value = ''; errors.value = {};
    const payload = { objective: form.objective, evidence: form.evidence || null, outcome_score: form.outcome_score === '' ? null : form.outcome_score };
    if (action === 'log') payload.status = 'evidenced';
    if (action === 'close') payload.status = 'closed';
    try {
        const result = await auth.request('/plans/' + props.planId + '/weeks/' + props.week.id, { method: 'PATCH', body: JSON.stringify(payload) });
        emit('saved', result.data); success.value = action === 'close' ? 'Week closed. This record is now read-only.' : 'Progress saved.';
    } catch (e) {
        errors.value = e.details || {};
        error.value = e.status === 422 ? 'Please check the highlighted fields.' : e.status === 409 ? e.message + ' Refresh the plan to see the latest state.' : e.message + ' Your entries are kept here. If this request timed out, refresh before trying again.';
    } finally { busy.value = false; }
}
</script>
<template>
    <article class="week-card" :id="'week-' + week.id">
        <div class="card-heading"><div class="week-heading"><span class="week-number">{{ String(week.week_number).padStart(2, '0') }}</span><div><h3>Week {{ week.week_number }}</h3><p class="muted">{{ week.skill?.name }}</p></div></div><span class="status-tag" :class="week.status">{{ week.status }}</span></div>
        <p v-if="success" class="notice" role="status">{{ success }}</p>
        <template v-if="!editable || week.status === 'closed'">
            <dl class="week-readonly"><dt>Objective</dt><dd>{{ week.objective }}</dd><dt>Evidence</dt><dd>{{ week.evidence || 'No evidence recorded yet.' }}</dd><dt>Outcome score</dt><dd>{{ week.outcome_score ?? 'Not recorded' }}</dd></dl>
            <p class="muted">{{ week.status === 'closed' ? 'Closed weeks are read-only.' : 'Your administrator records progress for this plan.' }}</p>
        </template>
        <form v-else @submit.prevent="save('save')" novalidate>
            <div v-if="error" class="error-box" role="alert">{{ error }}</div>
            <fieldset :disabled="busy">
                <label :for="'objective-' + week.id">Objective</label><textarea :id="'objective-' + week.id" v-model="form.objective" rows="2" :aria-invalid="!!errors.objective" :aria-describedby="'objective-error-' + week.id"></textarea><small :id="'objective-error-' + week.id" class="field-error">{{ errors.objective?.[0] }}</small>
                <div class="form-columns"><div><label :for="'evidence-' + week.id">Evidence</label><textarea :id="'evidence-' + week.id" v-model="form.evidence" rows="3" placeholder="What did you build, test or learn?" :aria-invalid="!!errors.evidence" :aria-describedby="'evidence-error-' + week.id"></textarea><small :id="'evidence-error-' + week.id" class="field-error">{{ errors.evidence?.[0] }}</small></div>
                <div><label :for="'outcome-' + week.id">Outcome score <span class="muted">(0–100)</span></label><input :id="'outcome-' + week.id" v-model="form.outcome_score" type="number" min="0" max="100" step="0.01" :aria-invalid="!!errors.outcome_score" :aria-describedby="'outcome-error-' + week.id"><small :id="'outcome-error-' + week.id" class="field-error">{{ errors.outcome_score?.[0] }}</small></div></div>
                <div class="form-actions"><span v-if="busy" role="status">Saving…</span><button class="secondary" type="submit">Save draft</button><button v-if="week.status === 'planned'" class="primary" type="button" @click="save('log')">Log outcome ↗</button><button v-if="week.status === 'evidenced'" class="primary" type="button" @click="save('close')">Close week ✓</button></div>
            </fieldset>
        </form>
    </article>
</template>
