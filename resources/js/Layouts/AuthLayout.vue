<template>
    <!-- Ce gabarit est recopié dans `app.blade.php` pour la connexion, rendue sans JavaScript. -->
    <a class="skip-link" href="#main-content">Aller au contenu</a>
    <div class="min-h-screen bg-canvas">
        <div class="h-56 bg-rail" aria-hidden="true" />
        <main id="main-content" tabindex="-1" class="mx-auto -mt-44 grid w-full max-w-md grid-cols-[minmax(0,1fr)] gap-6 px-4 pb-12 outline-none">
            <header class="flex items-center gap-3 text-white" aria-label="En-tête">
                <a href="/" class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-lg bg-primary text-sm font-bold" aria-hidden="true">PTR</span>
                    <span class="grid leading-tight">
                        <span class="text-lg font-semibold">PTR Staff</span>
                        <span class="text-sm text-rail-ink">Espace de travail de PTR Niger</span>
                    </span>
                </a>
            </header>
            <slot />
        </main>
    </div>
</template>
