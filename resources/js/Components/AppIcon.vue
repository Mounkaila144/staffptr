<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    size: { type: Number, default: 20 },
});

// Tracés inline, sans bibliothèque ni requête : le socle interdit toute ressource tierce (NFR3)
// et chaque kilo-octet compte en 3G. Une icône est toujours décorative et doublée d'un libellé.
const paths = {
    home: ['M3 10.5 12 3l9 7.5', 'M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5'],
    approve: ['M9 12.5l2 2 4-4.5', 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z'],
    team: ['M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20', 'M10 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z', 'M20 20v-1.5a3.5 3.5 0 0 0-2.5-3.35', 'M15.5 4.15a3.5 3.5 0 0 1 0 6.7'],
    wallet: ['M4 7.5A2.5 2.5 0 0 1 6.5 5H18v3', 'M4 7.5V17a2 2 0 0 0 2 2h13a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1H6.5A2.5 2.5 0 0 1 4 7.5Z', 'M16 13.5h.01'],
    report: ['M8 4h8l4 4v11a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z', 'M16 4v4h4', 'M10 12h6', 'M10 16h6', 'M4 8v12'],
    target: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M12 16.5a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Z', 'M12 12h.01'],
    tasks: ['M9 6h11', 'M9 12h11', 'M9 18h11', 'M4 5.5l1 1 2-2', 'M4 11.5l1 1 2-2', 'M4 17.5l1 1 2-2'],
    project: ['M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z'],
    package: ['M21 8 12 3 3 8v8l9 5 9-5V8Z', 'M3 8l9 5 9-5', 'M12 13v8'],
    blocker: ['M12 9v4', 'M12 17h.01', 'M10.3 3.9 2.4 17.5A2 2 0 0 0 4.1 20.5h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z'],
    calendar: ['M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z', 'M4 10h16', 'M8 3v4', 'M16 3v4'],
    receipt: ['M6 3h12v18l-3-2-3 2-3-2-3 2V3Z', 'M9 8h6', 'M9 12h6', 'M9 16h3'],
    review: ['M4 5h16v11H8l-4 4V5Z', 'M8 9h8', 'M8 12h5'],
    pie: ['M12 3v9h9', 'M20.5 15.5A9 9 0 1 1 8.5 3.7'],
    chart: ['M4 20h16', 'M7 16v-5', 'M12 16V7', 'M17 16v-8'],
    shield: ['M12 3 5 6v5c0 4.5 3 8.3 7 10 4-1.7 7-5.5 7-10V6l-7-3Z'],
    coins: ['M9 11c3.3 0 6-1.3 6-3s-2.7-3-6-3-6 1.3-6 3 2.7 3 6 3Z', 'M3 8v4c0 1.7 2.7 3 6 3s6-1.3 6-3V8', 'M9 15v4c0 1.7 2.7 3 6 3s6-1.3 6-3v-6', 'M15 13c3.3 0 6-1.3 6-3'],
    scale: ['M12 4v16', 'M7 20h10', 'M5 8h14', 'M5 8l-3 6a3 3 0 0 0 6 0L5 8Z', 'M19 8l-3 6a3 3 0 0 0 6 0l-3-6Z'],
    building: ['M4 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16', 'M15 9h4a1 1 0 0 1 1 1v11', 'M3 21h18', 'M8 8h3', 'M8 12h3', 'M8 16h3'],
    intern: ['M22 9 12 4 2 9l10 5 10-5Z', 'M6 11v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5'],
    clock: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M12 7v5l3 2'],
    document: ['M7 3h7l5 5v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z', 'M14 3v5h5', 'M9 13h6', 'M9 17h6'],
    search: ['M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z', 'M20 20l-4-4'],
    export: ['M12 4v11', 'M7.5 10.5 12 15l4.5-4.5', 'M5 19h14'],
    settings: ['M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z', 'M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z'],
    key: ['M15.5 7.5a3.5 3.5 0 1 1-2.6 5.8L5 21H3v-2l1-1h2v-2h2l1.2-1.2', 'M16.5 6.5h.01'],
    log: ['M4 6h16', 'M4 12h16', 'M4 18h10'],
    login: ['M15 3h4a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1h-4', 'M10 17l5-5-5-5', 'M15 12H3'],
    user: ['M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M4 21a8 8 0 0 1 16 0'],
    logout: ['M9 21H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h4', 'M16 17l5-5-5-5', 'M21 12H9'],
    bell: ['M6 8a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8Z', 'M10.3 20a2 2 0 0 0 3.4 0'],
    more: ['M5 12h.01', 'M12 12h.01', 'M19 12h.01'],
    close: ['M6 6l12 12', 'M18 6 6 18'],
    back: ['M15 18l-6-6 6-6'],
    inbox: ['M3 13h5l1.5 3h5L16 13h5', 'M5.5 5h13L21 13v6a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-6l2.5-8Z'],
    expense: ['M12 3v18', 'M16.5 7H10a3 3 0 0 0 0 6h4a3 3 0 0 1 0 6H7'],
    contract: ['M8 4h8l4 4v11a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z', 'M16 4v4h4', 'M10 16c1-1.5 2-1.5 3 0s2 1.5 3 0'],
    heart: ['M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z'],
};

const segments = computed(() => paths[props.name] ?? paths.document);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.75"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        focusable="false"
        class="shrink-0"
    >
        <path v-for="segment in segments" :key="segment" :d="segment" />
    </svg>
</template>
