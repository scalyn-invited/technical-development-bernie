<script setup>
import { inject, ref } from 'vue';
import { useRouter } from 'vue-router';
const { state, auth } = inject('session');
const router = useRouter();
const email = ref('');
const password = ref('');
const visible = ref(false);
const busy = ref(false);
const error = ref('');
const errors = ref({});
async function submit() {
    if (busy.value) return;
    busy.value = true; error.value = ''; errors.value = {}; state.notice = '';
    try {
        await auth.login(email.value.trim(), password.value);
        password.value = '';
        await router.replace('/workspace');
    } catch (e) {
        error.value = e.status === 401 ? 'That email and password do not match. Please try again.' : e.message;
        errors.value = e.details || {};
    } finally { busy.value = false; }
}
</script>
<template>
    <main class="login-layout">
        <section class="story-panel" aria-labelledby="story-title">
            <a class="wordmark light" href="/login"><span class="brand-mark">s</span>scalyn<span class="wordmark-divider"></span><span class="product-label">Development workspace</span></a>
            <div class="story-content">
                <span class="eyebrow"><span class="tiny-dot"></span> SMALL STEPS. MEANINGFUL PROGRESS.</span>
                <h1 id="story-title">Make room <br>for your <br><em>next chapter.</em></h1>
                <p>A clearer picture of where you are.<br>A focused path to where you want to be.</p>
                <div class="journey-art" aria-label="Programme journey: baseline, practice, growth">
                    <div class="art-grid"></div>
                    <div class="art-line"></div>
                    <div class="milestone first"><span>01</span><strong>Find your baseline</strong></div>
                    <div class="milestone second"><span>02</span><strong>Build with intention</strong></div>
                    <div class="milestone third"><span>03</span><strong>See your growth</strong></div>
                    <span class="art-caption">A LITTLE BETTER, EVERY WEEK ↗</span>
                </div>
            </div>
            <footer class="story-footer"><span>Training Assessment Tracker</span><span>Built for your development ↗</span></footer>
        </section>
        <section class="form-panel" aria-labelledby="login-title">
            <div class="form-top"><span class="pill"><span class="tiny-dot"></span> YOUR DEVELOPMENT, IN FOCUS</span></div>
            <div class="login-card">
                <span class="section-number">01 / WELCOME BACK</span>
                <h2 id="login-title">Good to see you.</h2>
                <p class="muted intro">Sign in to continue your development journey.</p>
                <div v-if="state.notice" class="notice" role="status">{{ state.notice }}</div>
                <div v-if="error" class="error-box" role="alert">{{ error }}</div>
                <form @submit.prevent="submit">
                    <label for="email">Email address</label>
                    <input id="email" v-model="email" type="email" autocomplete="username" placeholder="you@company.com" required :disabled="busy" :aria-invalid="!!errors.email" aria-describedby="email-error">
                    <small id="email-error" class="field-error">{{ errors.email?.[0] }}</small>
                    <label for="password">Password</label>
                    <div class="password-field">
                        <input id="password" v-model="password" :type="visible ? 'text' : 'password'" autocomplete="current-password" placeholder="Enter your password" required :disabled="busy" :aria-invalid="!!errors.password" aria-describedby="password-error">
                        <button type="button" class="reveal" :aria-label="visible ? 'Hide password' : 'Show password'" :aria-pressed="visible" @click="visible = !visible">{{ visible ? 'Hide' : 'Show' }}</button>
                    </div>
                    <small id="password-error" class="field-error">{{ errors.password?.[0] }}</small>
                    <button class="primary submit" type="submit" :disabled="busy"><span>{{ busy ? 'Signing you in…' : 'Sign in to your workspace' }}</span><span aria-hidden="true">{{ busy ? '◌' : '↗' }}</span></button>
                </form>
                <p class="help">New member? <RouterLink to="/register">Create your account</RouterLink></p>
                <div class="login-note"><span class="note-icon" aria-hidden="true">◎</span><p>Your skills. Your progress.<br><strong>One place to keep moving forward.</strong></p></div>
            </div>
            <footer class="form-footer"><span>Scalyn OPC</span><span>Learn with purpose.</span></footer>
        </section>
    </main>
</template>
