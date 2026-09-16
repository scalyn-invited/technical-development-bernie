import { createApp, reactive } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import Login from './pages/Login.vue';
import Workspace from './pages/Workspace.vue';
import Plans from './pages/Plans.vue';
import CreatePlan from './pages/CreatePlan.vue';
import Register from './pages/Register.vue';
import PlanDetail from './pages/PlanDetail.vue';
import OpenWeeks from './pages/OpenWeeks.vue';
import { createAuthClient } from './auth-client';

const state = reactive({ user: null, notice: '', checking: false });
const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: '/workspace' },
        { path: '/login', component: Login },
        { path: '/register', component: Register },
        { path: '/plans/new', component: CreatePlan, meta: { protected: true } },
        { path: '/workspace', component: Workspace, meta: { protected: true } },
        { path: '/plans', component: Plans, meta: { protected: true } },
        { path: '/plans/:plan', component: PlanDetail, meta: { protected: true } },
        { path: '/open-weeks', component: OpenWeeks, meta: { protected: true } },
        { path: '/:pathMatch(.*)*', redirect: '/workspace' },
    ],
});
let storage;
try { storage = window.sessionStorage; } catch { storage = { getItem: () => null, setItem: () => { throw new Error(); }, removeItem: () => {} }; }
const auth = createAuthClient({ state, storage, onExpired: () => router.replace('/login') });
router.beforeEach(async to => {
    if (!to.meta.protected) return true;
    state.checking = true;
    try {
        return await auth.restore() ? true : '/login';
    } catch (error) {
        state.notice = error.status === 401 ? state.notice : error.message;
        return '/login';
    } finally { state.checking = false; }
});
createApp(App).provide('session', { state, auth }).use(router).mount('#app');
import '../css/screens.css';
