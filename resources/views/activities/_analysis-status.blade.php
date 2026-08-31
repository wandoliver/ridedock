@if (in_array($status, ['pending', 'processing'], true))
    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Analyse läuft</span>
@elseif ($status === 'completed')
    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Analysiert</span>
@elseif ($status === 'failed')
    <span class="rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">Erneut versuchen</span>
@endif
