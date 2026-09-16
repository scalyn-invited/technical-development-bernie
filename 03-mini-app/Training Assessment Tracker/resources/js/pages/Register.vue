<script setup>
import { inject, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
const { auth } = inject('session'), router = useRouter();
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' });
const busy = ref(false), error = ref(''), errors = ref({});
async function submit() {
    if (busy.value) return;
    busy.value = true; error.value = ''; errors.value = {};
    try { await auth.register(form); form.password = ''; form.password_confirmation = ''; await router.replace('/workspace'); }
    catch (e) { error.value = e.message; errors.value = e.details || {}; }
    finally { busy.value = false; }
}
</script>
<template>
<main class="registration-page"><section class="week-card">
<RouterLink to="/login" class="back-link">← Sign in</RouterLink><span class="section-number">YOUR NEXT CHAPTER</span><h1>Join as a member<span class="green">.</span></h1><p class="muted">Create your account. An administrator will assign your development plan.</p>
<p v-if="error" class="error-box" role="alert">{{ error }}</p>
<form @submit.prevent="submit" novalidate><fieldset :disabled="busy">
<template v-for="field in [{key:'name',label:'Full name',type:'text',auto:'name'},{key:'email',label:'Email address',type:'email',auto:'username'},{key:'password',label:'Password (at least 8 characters)',type:'password',auto:'new-password'},{key:'password_confirmation',label:'Confirm password',type:'password',auto:'new-password'}]" :key="field.key">
<label :for="'register-' + field.key">{{ field.label }}</label><input :id="'register-' + field.key" v-model="form[field.key]" :type="field.type" :autocomplete="field.auto" :aria-invalid="!!errors[field.key]" :aria-describedby="'error-' + field.key"><small :id="'error-' + field.key" class="field-error">{{ errors[field.key]?.[0] }}</small>
</template><button type="submit" class="primary" :disabled="busy">{{ busy ? 'Creating account…' : 'Create member account' }}</button>
</fieldset></form></section></main>
</template>
