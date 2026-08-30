<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Bei RideDock anmelden.">
    <title>Anmelden · RideDock</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-slate-950 antialiased">
    <main class="grid min-h-screen place-items-center px-5 py-12">
        <div class="w-full max-w-md">
            <a href="{{ route('dashboard') }}" class="mx-auto flex w-fit items-center gap-3" aria-label="Zur RideDock Übersicht">
                <span class="grid size-10 place-items-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-900/15">
                    <svg viewBox="0 0 24 24" class="size-5" aria-hidden="true"><path d="M5 17.5 9.2 6h5.6L19 17.5M7 13h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="text-xl font-semibold tracking-tight">RideDock</span>
            </a>

            <section class="mt-8 rounded-3xl border border-slate-200 bg-white p-7 shadow-sm shadow-slate-950/[0.03] sm:p-9">
                <p class="text-sm font-medium text-emerald-700">Willkommen zurück</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Anmelden</h1>
                <p class="mt-3 text-sm leading-6 text-slate-500">Öffne deine private Fahrtenübersicht und verwalte deine Verbindungen.</p>

                <div class="mt-7">
                    @include('auth._login-form')
                </div>

                <p class="mt-6 text-center text-sm text-slate-500">Noch kein Konto? <a href="{{ route('register') }}" class="font-medium text-emerald-700 hover:text-emerald-900">Konto erstellen</a></p>
            </section>
        </div>
    </main>
</body>
</html>
