import { ref, watch, onScopeDispose } from 'vue';

export function useResource(path, request) {
    const data = ref(null), loading = ref(false), error = ref('');
    let generation = 0;
    async function load() {
        const run = ++generation;
        loading.value = true; error.value = ''; data.value = null;
        try {
            const result = await request(path());
            if (run === generation) data.value = result;
        } catch (e) { if (run === generation) error.value = e.message; }
        finally { if (run === generation) loading.value = false; }
    }
    watch(path, load, { immediate: true });
    onScopeDispose(() => generation++);
    return { data, loading, error, load };
}
