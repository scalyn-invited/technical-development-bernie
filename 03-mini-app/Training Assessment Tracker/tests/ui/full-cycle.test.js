import { it, expect, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import CreatePlan from '../../resources/js/pages/CreatePlan.vue';
import PlanLifecycle from '../../resources/js/components/PlanLifecycle.vue';
import PlanComparison from '../../resources/js/components/PlanComparison.vue';
import Register from '../../resources/js/pages/Register.vue';
const { push, replace } = vi.hoisted(() => ({ push: vi.fn(), replace: vi.fn() }));
vi.mock('vue-router', () => ({ useRouter: () => ({ push, replace }) }));
const skill = { id: 7, name: 'Vue', is_active: true };
const baseline = { id: 3, skill_id: 7, type: 'baseline', score: '0.00', skill };
const plan = { id: 5, user_id: 2, status: 'draft', assessments: [baseline], weekly_entries: [] };
function render(component, request, props = {}, role = 'administrator') {
    return mount(component, { props, global: { provide: { session: { state: { user: { id: role === 'member' ? 2 : 1, role } }, auth: { request, register: request } } }, stubs: { WorkspaceShell: { template: '<main><slot /></main>' }, RouterLink: { template: '<a><slot /></a>' } } } });
}
it('creates a draft with the selected baseline and redirects once', async () => {
    let resolve;
    const request = vi.fn(path => path.startsWith('/members') ? Promise.resolve({ data: [{ id: 2, name: 'Member' }] }) : path.startsWith('/skills') ? Promise.resolve({ data: [skill] }) : new Promise(r => resolve = r));
    const w = render(CreatePlan, request); await flushPromises();
    await w.get('#plan-member').setValue('2'); await w.get('#key-gaps').setValue('Testing');
    await w.get('#weekly-focus').setValue('Write tests'); await w.get('input[type=checkbox]').setValue(true);
    await w.get('#baselines-score-0').setValue('0');
    await w.get('form').trigger('submit'); await w.get('form').trigger('submit');
    const writes = request.mock.calls.filter(([path]) => path === '/plans');
    expect(writes).toHaveLength(1);
    expect(JSON.parse(writes[0][1].body).baselines).toEqual([{ skill_id: 7, score: 0, note: '' }]);
    resolve({ data: { id: 5 } }); await flushPromises(); expect(push).toHaveBeenCalledWith('/plans/5'); w.unmount();
});
it('maps nested creation errors and retains input', async () => {
    const request = vi.fn(path => path.startsWith('/members') ? Promise.resolve({ data: [{ id: 2, name: 'Member' }] }) : path.startsWith('/skills') ? Promise.resolve({ data: [skill] }) : Promise.reject({ message: 'Validation failed', details: { 'baselines.0.score': ['Invalid baseline'] } }));
    const w = render(CreatePlan, request); await flushPromises();
    await w.get('input[type=checkbox]').setValue(true); await w.get('#baselines-score-0').setValue('101');
    await w.get('form').trigger('submit'); await flushPromises();
    expect(w.get('#baselines-error-0').text()).toBe('Invalid baseline');
    expect(w.get('#baselines-score-0').element.value).toBe('101'); w.unmount();
});
it('requires acknowledgement before activation and prevents duplicates', async () => {
    let resolve;
    const request = vi.fn(path => path.startsWith('/skills') ? Promise.resolve({ data: [skill] }) : new Promise(r => resolve = r));
    const w = render(PlanLifecycle, request, { plan }); await flushPromises();
    expect(w.get('button.primary').element.disabled).toBe(true);
    await w.get('.confirm-line input').setValue(true);
    await w.get('button.primary').trigger('click'); await w.get('button.primary').trigger('click');
    expect(request.mock.calls.filter(([path]) => path.endsWith('/activate'))).toHaveLength(1);
    resolve({ data: { id: 5, status: 'active' } }); await flushPromises();
    expect(w.emitted('changed')[0][0].transition.status).toBe('active'); w.unmount();
});
it('corrects a baseline using its scoped endpoint', async () => {
    const request = vi.fn(path => Promise.resolve(path.startsWith('/skills') ? { data: [skill] } : { data: { ...baseline, score: '25.00' } }));
    const w = render(PlanLifecycle, request, { plan }); await flushPromises();
    await w.get('.baseline-review button').trigger('click'); await w.get('#correct-score-3').setValue('25');
    await w.get('.baseline-review form').trigger('submit'); await flushPromises();
    expect(request).toHaveBeenCalledWith('/plans/5/assessments/3', expect.objectContaining({ method: 'PATCH' }));
    expect(w.emitted('changed')[0][0].assessment.score).toBe('25.00'); w.unmount();
});
it('submits the exact final set including retired skills and displays nested errors', async () => {
    const retired = { ...baseline, skill: { ...skill, is_active: false } };
    const request = vi.fn().mockRejectedValue({ message: 'Validation failed', details: { 'finals.0.score': ['Invalid final'] } });
    const w = render(PlanLifecycle, request, { plan: { ...plan, status: 'active', assessments: [retired] } });
    await w.get('#finals-score-0').setValue('101'); await w.get('.confirm-line input').setValue(true);
    await w.get('form').trigger('submit'); await flushPromises();
    expect(w.text()).toContain('Retired skill'); expect(w.get('#finals-error-0').text()).toBe('Invalid final');
    expect(JSON.parse(request.mock.calls[0][1].body).finals).toEqual([{ skill_id: 7, score: 101, note: '' }]); w.unmount();
});
it('blocks completion with open weeks and all writes for member/completed plans', () => {
    for (const [p, role] of [[{ ...plan, status: 'active', weekly_entries: [{ status: 'planned' }] }, 'administrator'], [plan, 'member'], [{ ...plan, status: 'completed' }, 'administrator']]) {
        const w = render(PlanLifecycle, vi.fn(), { plan: p }, role);
        expect(w.find('form').exists()).toBe(false); w.unmount();
    }
});
it('requires final confirmation, submits once and becomes read-only after completion', async () => {
    let resolve;
    const request = vi.fn(() => new Promise(r => resolve = r));
    const w = render(PlanLifecycle, request, { plan: { ...plan, status: 'active' } });
    await w.get('form').trigger('submit'); expect(request).not.toHaveBeenCalled();
    await w.get('#finals-score-0').setValue('80'); await w.get('.confirm-line input').setValue(true);
    await w.get('form').trigger('submit'); await w.get('form').trigger('submit');
    expect(request).toHaveBeenCalledTimes(1); expect(w.get('fieldset').element.disabled).toBe(true);
    resolve({ data: { id: 5, status: 'completed' } }); await flushPromises();
    expect(w.emitted('changed')[0][0].transition.status).toBe('completed');
    await w.setProps({ plan: { ...plan, status: 'completed' } });
    expect(w.find('form').exists()).toBe(false); expect(w.text()).toContain('immutable'); w.unmount();
});
it('shows pending values separately from zero and uses server-computed movement', async () => {
    const w = render(PlanComparison, vi.fn().mockResolvedValue({ data: [{ skill_id: 7, skill_name: 'Vue', is_active: false, baseline_score: '0.00', final_score: null, delta: null }], summary: { compared_skills: 0, pending_skills: 1, average_movement: null } }), { planId: 5 });
    await flushPromises(); expect(w.text()).toContain('Pending'); expect(w.text()).toContain('0.00'); expect(w.text()).toContain('Not available yet'); expect(w.text()).toContain('Retired'); w.unmount();
});
it('registers only member identity fields and keeps errors visible', async () => {
    const request = vi.fn().mockRejectedValue({ message: 'Validation failed', details: { email: ['Email already used'] } });
    const w = render(Register, request);
    await w.get('#register-email').setValue('member@example.test'); await w.get('form').trigger('submit'); await flushPromises();
    expect(w.get('#error-email').text()).toBe('Email already used');
    expect(request.mock.calls[0][0]).not.toHaveProperty('role'); w.unmount();
});
