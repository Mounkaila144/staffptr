<script setup>
import { computed, ref } from "vue";
import { useMoney } from "../Composables/useMoney";

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: [String, Number], default: "" },
    error: { type: String, default: "" },
    required: { type: Boolean, default: false },
    variant: { type: String, default: "text" },
    autocomplete: { type: String, default: "" },
    placeholder: { type: String, default: "" },
    // `as` vaut 'input' par défaut : les formulaires existants gardent
    // exactement le rendu qu'ils avaient.
    as: { type: String, default: "input" },
    // Aide persistante sous le libellé. Volontairement pas une infobulle au
    // survol : sur téléphone il n'y a pas de survol, et l'application est
    // conçue pour le téléphone d'abord.
    hint: { type: String, default: "" },
    // Affiche « facultatif » quand le champ ne l'est pas. Obligatoire et
    // facultatif sont ainsi tous deux nommés, jamais déduits d'une absence.
    optionalLabel: { type: Boolean, default: false },
    options: { type: Array, default: () => [] },
    rows: { type: Number, default: 4 },
    maxlength: { type: Number, default: null },
});
const emit = defineEmits(["update:modelValue", "blur"]);
const input = ref(null);
const { formatAmount } = useMoney();
const hintId = computed(() => (props.hint ? `${props.id}-hint` : null));
const errorId = computed(() => (props.error ? `${props.id}-error` : null));
const describedBy = computed(
    () => [hintId.value, errorId.value].filter(Boolean).join(" ") || undefined,
);
const displayedValue = computed(() =>
    props.variant === "money" && props.modelValue !== ""
        ? formatAmount(props.modelValue)
        : props.modelValue,
);
const inputType = computed(
    () =>
        ({
            password: "password",
            phone: "tel",
            date: "date",
            time: "time",
            number: "number",
            "datetime-local": "datetime-local",
        })[props.variant] ?? "text",
);
const inputMode = computed(() => {
    if (["money", "code", "number"].includes(props.variant)) return "numeric";
    if (props.variant === "phone") return "tel";
    return undefined;
});
const borderClass = computed(() =>
    props.error ? "border-2 border-danger" : "border-separator",
);

defineExpose({ focus: () => input.value?.focus() });

function update(event) {
    const value =
        props.variant === "money"
            ? event.target.value.split(",")[0].replace(/\D/g, "")
            : event.target.value;
    emit("update:modelValue", value);
}
</script>

<template>
    <div class="grid gap-2">
        <label :for="id" class="text-field-label font-semibold">
            {{ label }}
            <span v-if="required" aria-hidden="true" class="text-danger">✱</span
            ><span v-if="required" class="sr-only"> obligatoire</span
            ><span
                v-else-if="optionalLabel"
                class="font-normal text-ink-secondary"
            >
                (facultatif)</span
            >
        </label>
        <p
            v-if="hint"
            :id="hintId"
            class="text-sm font-normal text-ink-secondary"
        >
            {{ hint }}
        </p>

        <textarea
            v-if="as === 'textarea'"
            :id="id"
            ref="input"
            :name="id"
            :value="modelValue"
            :required="required"
            :rows="rows"
            :maxlength="maxlength || undefined"
            :placeholder="placeholder || undefined"
            :aria-invalid="error ? 'true' : 'false'"
            :aria-describedby="describedBy"
            class="min-w-0 rounded-lg border bg-surface p-3 text-base"
            :class="borderClass"
            @input="emit('update:modelValue', $event.target.value)"
            @blur="emit('blur')"
        />

        <select
            v-else-if="as === 'select'"
            :id="id"
            ref="input"
            :name="id"
            :value="modelValue"
            :required="required"
            :aria-invalid="error ? 'true' : 'false'"
            :aria-describedby="describedBy"
            class="min-h-12 min-w-0 rounded-lg border bg-surface px-3 text-base"
            :class="borderClass"
            @change="emit('update:modelValue', $event.target.value)"
            @blur="emit('blur')"
        >
            <option v-if="placeholder" value="" disabled>
                {{ placeholder }}
            </option>
            <option
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
        </select>

        <div
            v-else
            class="flex min-h-12 items-center rounded-lg border bg-surface"
            :class="borderClass"
        >
            <span v-if="variant === 'phone'" class="pl-3 text-ink-secondary"
                >+227</span
            >
            <input
                :id="id"
                ref="input"
                :name="id"
                :value="displayedValue"
                :required="required"
                :autocomplete="autocomplete || undefined"
                :placeholder="placeholder || undefined"
                :inputmode="inputMode"
                :type="inputType"
                :aria-invalid="error ? 'true' : 'false'"
                :aria-describedby="describedBy"
                class="min-h-11 min-w-0 flex-1 rounded-lg bg-transparent px-3 text-base"
                @input="update"
                @blur="emit('blur')"
            />
            <span v-if="variant === 'money'" class="pr-3 text-ink-secondary"
                >F CFA</span
            >
        </div>

        <p v-if="error" :id="errorId" class="text-sm font-semibold text-danger">
            ⚠ {{ error }}
        </p>
    </div>
</template>
