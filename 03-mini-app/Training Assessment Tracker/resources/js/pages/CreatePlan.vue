<script setup>
import { inject, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import WorkspaceShell from '../components/WorkspaceShell.vue';
import ScreenState from '../components/ScreenState.vue';
import ScoreFields from '../components/ScoreFields.vue';
import { useResource } from '../use-resource';
const { state, auth } = inject('session'), router = useRouter();
const memberPage = ref(1), search = ref(''), skillPage = ref(1);
const members = useResource(() => '/members/eligible?page=' + memberPage.value + '&search=' + encodeURIComponent(search.value), path => state.user?.role === 'administrator' ? auth.request(path) : Promise.resolve({ data: [] }));
const skills = useResource(() => '/skills?active=1&per_page=20&page=' + skillPage.value, path => state.user?.role === 'administrator' ? auth.request(path) : Promise.resolve({ data: [] }));
const form = reactive({ user_id: '', key_gaps: '', weekly_focus: '', baselines: [] });
watch([search, memberPage], () => { form.user_id = ''; });
const busy = ref(false), error = ref(''), errors = ref({});
function toggle(skill) {
    const index = form.baselines.findIndex(row => row.skill_id === skill.id);
    if (index >= 0) form.baselines.splice(index, 1);
    else form.baselines.push({ skill_id: skill.id, name: skill.name, score: '', note: '' });
}
async function submit() {
    if (busy.value) return;
    busy.value = true; error.value = ''; errors.value = {};
    try {
        const result = await auth.request('/plans', { method: 'POST', body: JSON.stringify({ ...form, baselines: form.baselines.map(({ skill_id, score, note }) => ({ skill_id, score, note })) }) });
        await router.push('/plans/' + result.data.id);
    } catch (e) { errors.value = e.details || {}; error.value = e.message + ' Your entries are retained. After a timeout, check the directory before retrying.'; }
    finally { busy.value = false; }
}
</script>
<template>
<WorkspaceShell>
<RouterLink to="/plans" class="back-link">← Development plans</RouterLink>
<div class="page-heading"><div><span class="section-number">A PURPOSEFUL START</span><h1>Create a plan<span class="green">.</span></h1><p class="muted">Choose a member, define the focus, and capture their starting point.</p></div></div>
<p v-if="state.user?.role !== 'administrator'" class="notice">Only administrators can create plans.</p>
<form v-else class="week-card" @submit.prevent="submit" novalidate>
<div v-if="error" class="error-box" role="alert">{{ error }}</div>
<fieldset :disabled="busy">
<h2>01 / Member and direction</h2>
<label for="member-search">Find an unassigned member</label><input id="member-search" v-model="search" @input="memberPage = 1" placeholder="Search by name">
<ScreenState :loading="members.loading.value" :error="members.error.value" :empty="!members.data.value?.data?.length" title="No unassigned members" description="A member can register from the login page. Each member can have one plan per cycle." @retry="members.load">
<label for="plan-member">Member</label><select id="plan-member" v-model="form.user_id"><option disabled value="">Select member</option><option v-for="member in members.data.value?.data" :key="member.id" :value="member.id">{{ member.name }} · #{{ member.id }}</option></select>
</ScreenState>
<div class="form-actions"><button type="button" class="secondary" :disabled="memberPage === 1 || members.loading.value" @click="memberPage--">Previous members</button><button type="button" class="secondary" :disabled="!members.data.value?.next_page_url || members.loading.value" @click="memberPage++">More members</button></div>
<small class="field-error">{{ errors.user_id?.[0] }}</small>
<label for="key-gaps">Key gaps</label><textarea id="key-gaps" v-model="form.key_gaps" rows="2" maxlength="2000"></textarea><small class="field-error">{{ errors.key_gaps?.[0] }}</small>
<label for="weekly-focus">Weekly focus</label><textarea id="weekly-focus" v-model="form.weekly_focus" rows="2" maxlength="2000"></textarea><small class="field-error">{{ errors.weekly_focus?.[0] }}</small>
<h2>02 / Skills and baseline scores</h2>
<p class="muted">Every selected skill needs a score. Zero is valid. Draft scores can be corrected before activation.</p>
<ScreenState :loading="skills.loading.value" :error="skills.error.value" :empty="!skills.data.value?.data?.length" title="No active skills" description="An administrator must add or reactivate catalogue skills before creating a plan." @retry="skills.load">
<div class="skill-picker"><label v-for="skill in skills.data.value?.data" :key="skill.id"><input type="checkbox" :checked="form.baselines.some(row => row.skill_id === skill.id)" @change="toggle(skill)">{{ skill.name }}</label></div>
</ScreenState>
<div class="form-actions"><button type="button" class="secondary" :disabled="skillPage === 1 || skills.loading.value" @click="skillPage--">Previous skills</button><button type="button" class="secondary" :disabled="!skills.data.value?.links?.next || skills.loading.value" @click="skillPage++">More skills</button></div>
<small class="field-error">{{ errors.baselines?.[0] }}</small>
<ScoreFields :rows="form.baselines" :errors="errors" prefix="baselines" />
<button class="primary" :disabled="busy || !form.user_id || !form.baselines.length" type="submit">{{ busy ? 'Saving…' : 'Save draft plan ↗' }}</button>
</fieldset>
</form>
</WorkspaceShell>
</template>
