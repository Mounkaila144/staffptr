<script setup>
import { router } from '@inertiajs/vue3';

/*
 * Colonnes : `{ key, label, align?: 'right', primary?: true, hideOnMobile?: true }`.
 * Une cellule se personnalise par le slot `cell-<key>` ({ row, value }), sinon la valeur brute
 * de `row[key]` s'affiche. La colonne `primary` titre la carte sur téléphone.
 */
const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, required: true },
    rowKey: { type: String, default: 'id' },
    // Fonction `(row) => url` : toute la ligne devient cliquable, le lien reste dans la cellule
    // principale pour le clavier et les lecteurs d'écran.
    rowHref: { type: Function, default: null },
    caption: { type: String, default: '' },
});

function value(row, column) {
    return column.key.split('.').reduce((current, part) => current?.[part], row);
}

function openRow(row, event) {
    if (!props.rowHref || event.target.closest('a, button, input, select, textarea, label')) {
        return;
    }
    router.visit(props.rowHref(row));
}

const primaryColumn = () => props.columns.find((column) => column.primary) ?? props.columns[0];
</script>

<template>
    <div class="min-w-0 overflow-hidden rounded-xl border border-separator bg-surface">
        <!-- Ordinateur et tablette : un vrai tableau, qui défile seul s'il déborde. -->
        <div class="hidden min-w-0 overflow-x-auto md:block">
            <table class="min-w-full">
                <caption v-if="caption" class="sr-only">{{ caption }}</caption>
                <thead class="bg-neutral-soft/70">
                    <tr>
                        <th v-for="column in columns" :key="column.key" scope="col" :class="column.align === 'right' ? 'text-right' : ''">{{ column.label }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row[rowKey]" :class="rowHref ? 'cursor-pointer' : ''" @click="openRow(row, $event)">
                        <td v-for="column in columns" :key="column.key" :class="[column.align === 'right' ? 'text-right whitespace-nowrap' : '', column.primary ? 'font-medium text-ink' : 'text-ink-secondary']">
                            <slot :name="`cell-${column.key}`" :row="row" :value="value(row, column)">
                                <a v-if="column.primary && rowHref" :href="rowHref(row)" class="font-semibold text-ink hover:text-primary">{{ value(row, column) }}</a>
                                <template v-else>{{ value(row, column) ?? '—' }}</template>
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Téléphone : une carte par ligne, libellé et valeur l'un sous l'autre. -->
        <ul class="divide-y divide-separator md:hidden">
            <li v-for="row in rows" :key="row[rowKey]" class="grid min-w-0 gap-2 p-4" @click="openRow(row, $event)">
                <div class="min-w-0 break-words font-semibold text-ink">
                    <slot :name="`cell-${primaryColumn().key}`" :row="row" :value="value(row, primaryColumn())">
                        <a v-if="rowHref" :href="rowHref(row)" class="hover:text-primary">{{ value(row, primaryColumn()) }}</a>
                        <template v-else>{{ value(row, primaryColumn()) }}</template>
                    </slot>
                </div>
                <dl class="grid min-w-0 grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                    <template v-for="column in columns.filter((item) => item !== primaryColumn() && !item.hideOnMobile)" :key="column.key">
                        <dt class="text-ink-muted">{{ column.label }}</dt>
                        <dd class="min-w-0 break-words text-right text-ink-secondary">
                            <slot :name="`cell-${column.key}`" :row="row" :value="value(row, column)">{{ value(row, column) ?? '—' }}</slot>
                        </dd>
                    </template>
                </dl>
            </li>
        </ul>

        <div v-if="$slots.footer" class="border-t border-separator px-4 py-3">
            <slot name="footer" />
        </div>
    </div>
</template>
