<template>
    <a class="skip-link" href="#main-content">Aller au contenu</a>
    <header class="flex h-14 items-center border-b border-separator bg-surface px-4" aria-label="En-tête">
        <a href="/" class="touch-target inline-flex items-center font-semibold text-primary">PTR Staff</a>
    </header>
    <main id="main-content" tabindex="-1" class="mx-auto flex min-h-[calc(100vh-3.5rem)] max-w-content items-center px-4 py-8">
        <slot />
    </main>
</template>
