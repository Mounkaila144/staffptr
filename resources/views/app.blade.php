<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#F7F7F5">
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
                <header class="flex h-14 items-center border-b border-separator bg-surface px-4" aria-label="En-tête">
                    <a href="/" class="touch-target inline-flex items-center font-semibold text-primary">PTR Staff</a>
                </header>
                <main id="main-content" tabindex="-1" class="mx-auto flex min-h-[calc(100vh-3.5rem)] max-w-content items-center px-4 py-8">
                    <section class="w-full rounded-xl border border-separator bg-surface p-5 shadow-sm sm:p-8" aria-labelledby="login-title">
                        <div class="mb-6 grid gap-2">
                            <h1 id="login-title" class="text-screen-title">Connexion</h1>
                            <p class="text-ink-secondary">Entrez votre numéro du Niger et votre mot de passe.</p>
                        </div>
                        <form class="grid gap-5" method="POST" action="{{ route('login.store') }}">
                            @csrf
                            <div class="grid gap-2">
                                <label for="phone" class="text-field-label font-semibold">Numéro de téléphone <span aria-hidden="true" class="text-danger">✱</span><span class="sr-only"> obligatoire</span></label>
                                <div class="flex min-h-12 items-center rounded-lg border bg-surface {{ $errors->has('phone') ? 'border-2 border-danger' : 'border-separator' }}">
                                    <span class="pl-3 text-ink-secondary">+227</span>
                                    <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="90 12 34 56" required value="{{ old('phone') }}" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}" @if ($errors->has('phone')) aria-describedby="phone-error" @endif class="min-h-11 min-w-0 flex-1 rounded-lg bg-transparent px-3 text-base">
                                </div>
                                @error('phone')<p id="phone-error" class="text-sm font-semibold text-danger">⚠ {{ $message }}</p>@enderror
                            </div>
                            <div class="grid gap-2">
                                <label for="password" class="text-field-label font-semibold">Mot de passe <span aria-hidden="true" class="text-danger">✱</span><span class="sr-only"> obligatoire</span></label>
                                <div class="flex min-h-12 items-center rounded-lg border border-separator bg-surface">
                                    <input id="password" name="password" type="password" autocomplete="current-password" required class="min-h-11 min-w-0 flex-1 rounded-lg bg-transparent px-3 text-base">
                                </div>
                            </div>
                            <button type="submit" class="touch-target grid min-h-12 w-full items-center justify-items-center rounded-lg bg-primary px-4 py-2 font-semibold text-white md:w-auto">Se connecter</button>
                        </form>
                    </section>
                </main>
            </div>
        @else
            @inertia
        @endif
    </body>
</html>
