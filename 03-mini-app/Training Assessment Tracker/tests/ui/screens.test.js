import { describe, it, expect, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import Plans from '../../resources/js/pages/Plans.vue';
import OpenWeeks from '../../resources/js/pages/OpenWeeks.vue';
import PlanDetail from '../../resources/js/pages/PlanDetail.vue';
import WeekEditor from '../../resources/js/components/WeekEditor.vue';
import NewWeekForm from '../../resources/js/components/NewWeekForm.vue';
vi.mock('vue-router', () => ({ useRoute: () => ({ params: { plan: 1 } }) }));
const plan = { id: 1, user_id: 2, member: { name: 'Test Member' }, status: 'active', assessments: [], weekly_entries: [], skills_count: 2, current_week: 1 };
const week = { id: 1, development_plan_id: 1, week_number: 1, objective: 'Build a form', status: 'planned', skill: { name: 'Vue' }, plan };
function render(component, request, props = {}) {
    return mount(component, { props, global: { provide: { session: { state: { user: { id: 9, role: 'administrator' } }, auth: { request } } }, stubs: { PlanLifecycle: true, PlanComparison: true, WorkspaceShell: { template: '<main><slot /></main>' }, RouterLink: { template: '<a><slot /></a>' } } } });
}
for (const [name, component, populated, empty, marker, emptyMarker] of [
    ['plans', Plans, { data: [plan] }, { data: [] }, 'Test Member', 'No plans found'],
    ['queue', OpenWeeks, { data: [week] }, { data: [] }, 'Build a form', 'all caught up'],
    ['detail', PlanDetail, { data: plan }, { data: null }, 'Test Member', 'No plan available'],
]) {
    describe(name + ' screen states', () => {
        it('shows loading then populated data', async () => {
            let resolve; const request = vi.fn(() => new Promise(r => resolve = r));
            const wrapper = render(component, request);
            expect(wrapper.text()).toContain('Loading…');
            resolve(populated); await flushPromises();
            expect(wrapper.text()).toContain(marker);
            expect(wrapper.text()).not.toContain('Loading…'); wrapper.unmount();
        });
        it('shows empty state', async () => {
            const wrapper = render(component, vi.fn().mockResolvedValue(empty));
            await flushPromises(); expect(wrapper.text()).toContain(emptyMarker); wrapper.unmount();
        });
        it('shows failure and recovers on retry', async () => {
            const request = vi.fn().mockRejectedValueOnce(new Error('Offline')).mockResolvedValue(populated);
            const wrapper = render(component, request); await flushPromises();
            expect(wrapper.get('[role="alert"]').text()).toContain('Offline');
            await wrapper.get('.state-error button').trigger('click'); await flushPromises();
            expect(wrapper.text()).toContain(marker); wrapper.unmount();
        });
    });
}
it('ignores an older filter response', async () => {
    let first; const request = vi.fn().mockImplementationOnce(() => new Promise(r => first = r)).mockResolvedValue({ data: [] });
    const wrapper = render(Plans, request);
    await wrapper.get('select').setValue('completed'); await flushPromises();
    expect(request.mock.calls[1][0]).toContain('status=completed');
    first({ data: [plan] }); await flushPromises();
    expect(wrapper.text()).toContain('No plans found'); wrapper.unmount();
});
it('keeps evidence and displays 422 errors next to the fields', async () => {
    const request = vi.fn().mockRejectedValue({ status: 422, details: { evidence: ['Evidence required'], outcome_score: ['Score must be valid'] } });
    const wrapper = render(WeekEditor, request, { week, planId: 1, editable: true });
    await wrapper.get('#evidence-1').setValue('My work');
    await wrapper.get('.primary').trigger('click'); await flushPromises();
    expect(wrapper.get('#evidence-error-1').text()).toBe('Evidence required');
    expect(wrapper.get('#outcome-error-1').text()).toBe('Score must be valid');
    expect(wrapper.get('#evidence-1').element.value).toBe('My work'); wrapper.unmount();
});
for (const status of ['planned', 'evidenced']) {
    it('blocks duplicate ' + status + ' submissions and preserves zero scores', async () => {
        let resolve; const request = vi.fn(() => new Promise(r => resolve = r));
        const wrapper = render(WeekEditor, request, { week: { ...week, status, evidence: 'Evidence', outcome_score: 0 }, planId: 1, editable: true });
        await wrapper.get('.primary').trigger('click');
        expect(wrapper.get('fieldset').element.disabled).toBe(true);
        await wrapper.get('form').trigger('submit');
        expect(request).toHaveBeenCalledTimes(1);
        expect(JSON.parse(request.mock.calls[0][1].body).outcome_score).toBe(0);
        resolve({ data: { ...week, status: status === 'planned' ? 'evidenced' : 'closed' } }); await flushPromises();
        expect(wrapper.emitted('saved')).toHaveLength(1); wrapper.unmount();
    });
}
it('renders closed weeks and member access read-only', () => {
    for (const props of [{ week: { ...week, status: 'closed' }, editable: true }, { week, editable: false }]) {
        const wrapper = render(WeekEditor, vi.fn(), { ...props, planId: 1 });
        expect(wrapper.find('form').exists()).toBe(false); wrapper.unmount();
    }
});
it('creates the next week once and reports the returned record', async () => {
    let resolve; const request = vi.fn(() => new Promise(r => resolve = r));
    const wrapper = render(NewWeekForm, request, { plan, skills: [{ id: 3, name: 'Vue' }] });
    await wrapper.get('select').setValue('3'); await wrapper.get('textarea').setValue('Build a form');
    await wrapper.get('form').trigger('submit'); await wrapper.get('form').trigger('submit');
    expect(request).toHaveBeenCalledTimes(1);
    expect(JSON.parse(request.mock.calls[0][1].body).week_number).toBe(1);
    resolve({ data: week }); await flushPromises(); expect(wrapper.emitted('saved')[0][0]).toEqual(week); wrapper.unmount();
});
