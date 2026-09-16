<script setup>
defineProps({ rows: Array, errors: { type: Object, default: () => ({}) }, prefix: String });
</script>
<template>
<div v-for="(row, index) in rows" :key="row.skill_id" class="score-row">
    <div><strong>{{ row.name }}</strong><small v-if="row.is_active === false" class="muted">Retired skill · retained in this assessment</small></div>
    <div><label :for="prefix + '-score-' + index">Score (0–100)</label><input :id="prefix + '-score-' + index" v-model="row.score" type="number" min="0" max="100" step="0.01" :aria-invalid="!!errors[prefix + '.' + index + '.score']" :aria-describedby="prefix + '-error-' + index"><small class="field-error" :id="prefix + '-error-' + index">{{ errors[prefix + '.' + index + '.score']?.[0] }}</small></div>
    <div><label :for="prefix + '-note-' + index">Note (optional)</label><input :id="prefix + '-note-' + index" v-model="row.note" maxlength="2000"><small class="field-error">{{ errors[prefix + '.' + index + '.note']?.[0] || errors[prefix + '.' + index + '.skill_id']?.[0] }}</small></div>
</div>
</template>
