<script setup>
import { inject, ref, reactive } from 'vue';
const props = defineProps({ plan: Object, skills: Array });
const emit = defineEmits(['saved']);
const { auth } = inject('session');
const busy = ref(false), error = ref(''), errors = ref({});
const form = reactive({ skill_id: '', objective: '' });
async function submit() {
    if (busy.value) return;
    busy.value = true; error.value = ''; errors.value = {};
    try {
        const result = await auth.request('/plans/' + props.plan.id + '/weeks', { method: 'POST', body: JSON.stringify({ ...form, week_number: props.plan.weekly_entries.length + 1 }) });
        form.objective = ''; form.skill_id = '';
        emit('saved', result.data);
    } catch (e) { errors.value = e.details || {}; error.value = e.status === 422 ? 'Please check the highlighted fields.' : e.message + ' Your entries are kept. Refresh before retrying a timed-out request.'; }
    finally { busy.value = false; }
}
</script>
<template>
    <section class="week-card new-week"><h3>Plan your next week</h3><p class="muted">One skill. One clear objective.</p>
        <form @submit.prevent="submit" novalidate>
            <div v-if="error" class="error-box" role="alert">{{ error }}</div>
            <fieldset :disabled="busy">
                <label for="new-skill">Focus skill</label><select id="new-skill" v-model="form.skill_id" :aria-invalid="!!errors.skill_id" aria-describedby="new-skill-error"><option disabled value="">Choose a baseline skill</option><option v-for="skill in skills" :key="skill.id" :value="skill.id">{{ skill.name }}</option></select><small id="new-skill-error" class="field-error">{{ errors.skill_id?.[0] }}</small>
                <label for="new-objective">Objective</label><textarea id="new-objective" v-model="form.objective" rows="3" placeholder="What will this week move forward?" :aria-invalid="!!errors.objective" aria-describedby="new-objective-error"></textarea><small id="new-objective-error" class="field-error">{{ errors.objective?.[0] }}</small>
                <small v-if="errors.week_number" class="field-error" role="alert">{{ errors.week_number[0] }}</small>
                <button class="primary" type="submit">{{ busy ? 'Creating…' : 'Create week ' + (plan.weekly_entries.length + 1) + ' ↗' }}</button>
            </fieldset>
        </form>
    </section>
</template>
