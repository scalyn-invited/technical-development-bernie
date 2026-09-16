import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createAuthClient } from '../../resources/js/auth-client.js';

function setup(fetcher, stored = null) {
    const values = new Map(stored ? [['tracker.session', stored]] : []);
    const state = { user: null, notice: '' };
    let expired = 0;
    const storage = { getItem: k => values.get(k), setItem: (k,v) => values.set(k,v), removeItem: k => values.delete(k) };
    const auth = createAuthClient({ state, storage, fetcher, onExpired: () => expired++ });
    return { auth, state, values, expired: () => expired, storage };
}
const reply = (status, body) => ({ ok: status < 400, status, json: async () => body });

test('member registration establishes the session used by subsequent requests', async () => {
    const requests = [];
    const s = setup(async (url, options) => {
        requests.push([url, options]);
        return reply(201, { token: 'registered-token', data: { id: 8, role: 'member' } });
    });
    await s.auth.register({ name: 'Member', email: 'member@example.test', password: 'test-password', password_confirmation: 'test-password' });
    assert.equal(s.state.user.role, 'member');
    assert.equal(s.values.get('tracker.session'), 'registered-token');
    await s.auth.request('/me');
    assert.equal(requests[1][1].headers.Authorization, 'Bearer registered-token');
});

test('login saves a token and subsequent requests attach it', async () => {
    const requests = [];
    const s = setup(async (url, options) => { requests.push([url, options]); return reply(200, url.endsWith('login') ? { token: 'test-token', data: { name: 'Member' } } : { data: {} }); });
    await s.auth.login('user@example.test', 'password');
    assert.equal(s.values.get('tracker.session'), 'test-token');
    await s.auth.request('/me');
    assert.equal(requests[1][1].headers.Authorization, 'Bearer test-token');
    assert.equal(requests[1][1].credentials, 'omit');
});

test('fresh client restores saved session using real identity request', async () => {
    const s = setup(async () => reply(200, { data: { name: 'Member' } }), 'saved');
    assert.equal(await s.auth.restore(), true);
    assert.equal(s.state.user.name, 'Member');
});

test('guest cannot restore without a token', async () => {
    const s = setup(() => { throw new Error('must not fetch'); });
    assert.equal(await s.auth.restore(), false);
});

test('401 on protected calls clears session and signals redirect', async () => {
    const s = setup(async () => reply(401, { message: 'Unauthenticated.' }), 'expired');
    await assert.rejects(s.auth.request('/me'), { status: 401 });
    assert.equal(s.values.size, 0);
    assert.equal(s.expired(), 1);
    assert.match(s.state.notice, /session has ended/);
});

test('wrong password remains a login error, not an expiry redirect', async () => {
    const s = setup(async () => reply(401, { message: 'Invalid credentials.' }));
    await assert.rejects(s.auth.login('a@b.test', 'wrong'), { status: 401 });
    assert.equal(s.expired(), 0);
    assert.equal(s.values.size, 0);
});

test('logout clears the local token even when the server is unavailable', async () => {
    const s = setup(async () => { throw new TypeError('Network'); }, 'saved');
    await s.auth.logout();
    assert.equal(s.values.size, 0);
    assert.match(s.state.notice, /could not confirm/);
});

test('server failures preserve a potentially valid token', async () => {
    const s = setup(async () => reply(500, { message: 'An unexpected error occurred.' }), 'saved');
    await assert.rejects(s.auth.restore(), { status: 500 });
    assert.equal(s.values.get('tracker.session'), 'saved');
    assert.equal(s.expired(), 0);
});

test('malformed success and validation failures are actionable', async () => {
    const s = setup(async () => reply(422, { message: 'Validation failed.', details: { email: ['Email is required.'] } }));
    await assert.rejects(s.auth.login('', ''), error => error.details.email[0] === 'Email is required.');
    const broken = setup(async () => reply(200, null));
    await assert.rejects(broken.auth.login('a@b.test', 'pass'), /unreadable/);
});
