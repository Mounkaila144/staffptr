<script setup>
import { computed, ref } from 'vue';
import { useMoney } from '../Composables/useMoney';

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: [String, Number], default: '' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    variant: { type: String, default: 'text' },
});
const emit = defineEmits(['update:modelValue', 'blur']);
const input = ref(null);
const { formatAmount } = useMoney();
const describedBy = computed(() => props.error ? `${props.id}-error` : undefined);
const displayedValue = computed(() => props.variant === 'money' && props.modelValue !== ''
    ? formatAmount(props.modelValue)
    : props.modelValue);

defineExpose({ focus: () => input.value?.focus() });

function update(event) {
    const value = props.variant === 'money'
        ? event.target.value.split(',')[0].replace(/\D/g, '')
        : event.target.value;
    emit('update:modelValue', value);
}
</script>

<template>
    <div class="grid gap-2">
        <label :for="id" class="text-field-label font-semibold">
            {{ label }} <span v-if="required" aria-hidden="true" class="text-danger">✱</span><span v-if="required" class="sr-only"> obligatoire</span>
        </label>
        <div class="flex min-h-12 items-center rounded-lg border bg-surface" :class="error ? 'border-2 border-danger' : 'border-separator'">
            <span v-if="variant === 'phone'" class="pl-3 text-ink-secondary">+227</span>
            <input ref="input" :id="id" :value="displayedValue" :required="required" :inputmode="variant === 'money' ? 'numeric' : undefined" :type="variant === 'phone' ? 'tel' : 'text'" :aria-invalid="error ? 'true' : 'false'" :aria-describedby="describedBy" class="min-h-11 min-w-0 flex-1 rounded-lg bg-transparent px-3 text-base" @input="update" @blur="emit('blur')" />
            <span v-if="variant === 'money'" class="pr-3 text-ink-secondary">F CFA</span>
        </div>
        <p v-if="error" :id="`${id}-error`" class="text-sm font-semibold text-danger">⚠ {{ error }}</p>
    </div>
</template>
