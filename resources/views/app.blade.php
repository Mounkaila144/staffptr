<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0C1A30">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if (($page['component'] ?? null) === 'Identity/Login')
            <title>Connexion — PTR Staff</title>
        @endif
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="bg-canvas text-ink antialiased">
        @if (($page['component'] ?? null) === 'Identity/Login')
            <div id="app" data-page="{{ json_encode($page) }}">
                <a class="skip-link" href="#main-content">Aller au contenu</a>
                {{-- Copie statique de `AuthLayout.vue` : la connexion s'affiche avant tout JavaScript. --}}
                <div class="min-h-screen bg-canvas">
                <div class="h-56 bg-rail" aria-hidden="true"></div>
                <main id="main-content" tabindex="-1" class="mx-auto -mt-44 grid w-full max-w-md gap-6 px-4 pb-12 outline-none">
                    <header class="flex items-center gap-3 text-white" aria-label="En-tête">
                        <a href="/" class="flex items-center gap-3">
                            <span class="grid size-10 place-items-center rounded-lg bg-primary text-sm font-bold" aria-hidden="true">PTR</span>
                            <span class="grid leading-tight">
                                <span class="text-lg font-semibold">PTR Staff</span>
                                <span class="text-sm text-rail-ink">Espace de travail de PTR Niger</span>
                            </span>
                        </a>
                    </header>
                    <section class="w-full rounded-2xl border border-separator bg-surface p-6 shadow-raised sm:p-8" aria-labelledby="login-title">
                        <div class="mb-6 grid gap-2">
                            <h1 id="login-title" class="text-screen-title">Connexion</h1>
                            <p class="text-ink-secondary">Entrez votre numéro du Niger et votre mot de passe.</p>
                        </div>
                        <form class="grid gap-5" method="POST" action="{{ route('login.store') }}">
                            @csrf
                            <div class="grid gap-2">
                                <label for="phone" class="text-field-label font-semibold">Numéro de téléphone <span aria-hidden="true" class="text-danger">✱</span><span class="sr-only"> obligatoire</span></label>
                                <div class="flex min-h-12 items-center rounded-lg border bg-surface transition-shadow {{ $errors->has('phone') ? 'border-2 border-danger' : 'border-separator-strong focus-within:border-primary focus-within:ring-4 focus-within:ring-primary/15' }}">
                                    <span class="border-r border-separator py-1 pl-3 pr-3 text-ink-secondary">+227</span>
                                    <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="90 12 34 56" required value="{{ old('phone') }}" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}" @if ($errors->has('phone')) aria-describedby="phone-error" @endif class="min-h-11 min-w-0 flex-1 rounded-lg bg-transparent px-3 text-base outline-none">
                                </div>
                                @error('phone')<p id="phone-error" class="text-sm font-semibold text-danger">⚠ {{ $message }}</p>@enderror
                            </div>
                            <div class="grid gap-2">
                                <label for="password" class="text-field-label font-semibold">Mot de passe <span aria-hidden="true" class="text-danger">✱</span><span class="sr-only"> obligatoire</span></label>
                                <div class="flex min-h-12 items-center rounded-lg border border-separator-strong bg-surface transition-shadow focus-within:border-primary focus-within:ring-4 focus-within:ring-primary/15">
                                    <input id="password" name="password" type="password" autocomplete="current-password" required class="min-h-11 min-w-0 flex-1 rounded-lg bg-transparent px-3 text-base outline-none">
                                </div>
                            </div>
                            <button type="submit" class="touch-target grid min-h-12 w-full items-center justify-items-center rounded-lg bg-primary px-4 py-2 text-[0.9375rem] font-semibold text-white shadow-sm transition-colors duration-150 hover:bg-primary-strong md:min-h-11 md:w-auto">Se connecter</button>
                        </form>
                    </section>
                </main>
                </div>
            </div>
        @else
            @inertia
        @endif
    </body>
</html>
