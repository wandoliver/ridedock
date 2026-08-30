<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Trainingsprofil und KI-Analyse für RideDock konfigurieren.">
    <title>Training · Einstellungen · RideDock</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-slate-950 antialiased">
    <header class="border-b border-slate-200/80 bg-white/80 backdrop-blur">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-5 py-4 sm:px-8">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3" aria-label="Zur RideDock Übersicht">
                <span class="grid size-9 place-items-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-900/15">
                    <svg viewBox="0 0 24 24" class="size-5" aria-hidden="true"><path d="M5 17.5 9.2 6h5.6L19 17.5M7 13h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="text-lg font-semibold tracking-tight">RideDock</span>
            </a>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">
                <svg viewBox="0 0 20 20" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 4-6 6 6 6"/></svg>
                Übersicht
            </a>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-5 py-8 sm:px-8 sm:py-12">
        @if (session('status'))
            <div class="mb-8 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm text-emerald-900" role="status">
                <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-emerald-600 text-xs text-white">✓</span>
                <p>{{ session('status') }}</p>
            </div>
        @endif

        <section>
            <p class="text-sm font-medium text-emerald-700">Einstellungen</p>
            <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Training</h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600">Deine Ziele und Schwellenwerte bilden den Kontext für nachvollziehbare Analysen und spätere Trainingspläne.</p>
        </section>

        <nav class="mt-7 flex w-fit rounded-xl border border-slate-200 bg-white p-1 shadow-sm" aria-label="Einstellungsbereiche">
            <a href="{{ route('settings.training') }}" class="rounded-lg bg-slate-950 px-3 py-2 text-sm font-medium text-white" aria-current="page">Training & KI</a>
            <a href="{{ route('settings.connections') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">Verbindungen</a>
        </nav>

        <section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/[0.03] sm:p-8">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <p class="text-sm text-slate-500">KI-Status</p>
                    <h2 class="mt-1 text-xl font-semibold tracking-tight">Fahrtanalyse</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Übertragen werden nur berechnete Trainingswerte. GPS-Koordinaten, FIT-Dateien, Providerdaten und Aktivitätsnotizen bleiben in RideDock.</p>
                </div>
                <span class="w-fit rounded-full px-3 py-1.5 text-sm font-medium {{ $aiConfigured ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">
                    {{ $aiConfigured ? 'Konfiguriert' : 'API-Zugang fehlt' }}
                </span>
            </div>
            @unless ($aiConfigured)
                <p class="mt-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">Für echte Analysen müssen <code>OPENAI_API_KEY</code> und <code>OPENAI_MODEL</code> serverseitig gesetzt werden.</p>
            @endunless
        </section>

        <section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/[0.03] sm:p-8">
            <div>
                <p class="text-sm text-slate-500">Personalisierung</p>
                <h2 class="mt-1 text-xl font-semibold tracking-tight">Trainingsprofil</h2>
            </div>

            @if ($errors->any())
                <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
                    <p class="font-medium">Bitte prüfe deine Angaben.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @php
                $days = [
                    'monday' => 'Montag', 'tuesday' => 'Dienstag', 'wednesday' => 'Mittwoch',
                    'thursday' => 'Donnerstag', 'friday' => 'Freitag', 'saturday' => 'Samstag',
                    'sunday' => 'Sonntag',
                ];
                $selectedDays = (array) old('available_days', $profile?->available_days ?? []);
            @endphp

            <form method="POST" action="{{ route('settings.training.update') }}" class="mt-7 space-y-6">
                @csrf
                @method('PATCH')

                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="sm:col-span-2">
                        <span class="text-sm font-medium text-slate-700">Trainingsziel</span>
                        <textarea name="goal" rows="3" maxlength="500" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15" placeholder="Zum Beispiel: Im Juni eine 160-km-Tour kontrolliert fahren">{{ old('goal', $profile?->goal) }}</textarea>
                    </label>

                    <label>
                        <span class="text-sm font-medium text-slate-700">Zieldatum</span>
                        <input type="date" name="goal_date" value="{{ old('goal_date', $profile?->goal_date?->toDateString()) }}" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15">
                    </label>

                    <label>
                        <span class="text-sm font-medium text-slate-700">Erfahrung</span>
                        <select name="experience_level" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15">
                            <option value="">Nicht angegeben</option>
                            @foreach (['beginner' => 'Einsteiger', 'intermediate' => 'Fortgeschritten', 'advanced' => 'Erfahren'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('experience_level', $profile?->experience_level) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span class="text-sm font-medium text-slate-700">Stunden pro Woche <span class="font-normal text-slate-400">(optional)</span></span>
                        <input type="number" name="weekly_hours" min="1" max="40" step="0.5" value="{{ old('weekly_hours', $profile?->weekly_hours) }}" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15">
                        <span class="mt-2 block text-xs text-slate-500">Ohne Angabe orientiert sich der Plan konservativ an deinen letzten Wochen.</span>
                    </label>

                    <label>
                        <span class="text-sm font-medium text-slate-700">FTP <span class="font-normal text-slate-400">(optional)</span></span>
                        <div class="relative mt-2">
                            <input type="number" name="ftp_watts" min="50" max="600" value="{{ old('ftp_watts', $profile?->ftp_watts) }}" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 pr-12 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15">
                            <span class="pointer-events-none absolute inset-y-0 right-3.5 flex items-center text-sm text-slate-400">W</span>
                        </div>
                        <span class="mt-2 block text-xs text-slate-500">Wird nur verwendet, wenn du mit Leistungsmesser trainierst.</span>
                    </label>

                    <label>
                        <span class="text-sm font-medium text-slate-700">Maximale Herzfrequenz <span class="font-normal text-slate-400">(optional)</span></span>
                        <div class="relative mt-2">
                            <input type="number" name="max_heart_rate" min="100" max="230" value="{{ old('max_heart_rate', $profile?->max_heart_rate) }}" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 pr-16 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15">
                            <span class="pointer-events-none absolute inset-y-0 right-3.5 flex items-center text-sm text-slate-400">bpm</span>
                        </div>
                        <span class="mt-2 block text-xs text-slate-500">Wird nur verwendet, wenn Herzfrequenzdaten vorhanden sind.</span>
                    </label>
                </div>

                <fieldset>
                    <legend class="text-sm font-medium text-slate-700">Verfügbare Trainingstage</legend>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($days as $value => $label)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="available_days[]" value="{{ $value }}" class="peer sr-only" @checked(in_array($value, $selectedDays, true))>
                                <span class="block rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-600 transition peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-800">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Einschränkungen</span>
                    <textarea name="constraints" rows="3" maxlength="2000" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15" placeholder="Zum Beispiel: Unter der Woche maximal 60 Minuten">{{ old('constraints', $profile?->constraints) }}</textarea>
                    <span class="mt-2 block text-xs leading-5 text-slate-500">Diese Angabe wird bei einer angeforderten KI-Analyse mitgesendet. Keine medizinischen Details eintragen, die du nicht übermitteln möchtest.</span>
                </label>

                <div class="flex justify-end border-t border-slate-100 pt-6">
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700">Profil speichern</button>
                </div>
            </form>
        </section>

        <footer class="mt-8 flex flex-col gap-2 border-t border-slate-200 pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p>RideDock · Trainingseinstellungen</p>
            <p>Analysen werden nur auf deine Anfrage erzeugt.</p>
        </footer>
    </main>
</body>
</html>
