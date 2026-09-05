@php
    $intervalMatch = [];
    $hasIntervals = preg_match('/(\d+)\s*[×x]\s*(\d+)\s*(?:Minuten|min).*?(\d+)\s*(?:Minuten|min)\s*locker/iu', $details, $intervalMatch);
@endphp
@if($hasIntervals && (int) $intervalMatch[1] <= 12)
<span class="rd-block-copy mt-2 flex h-[5px] gap-[2px]" aria-hidden="true">
@for($interval = 0; $interval < (int) $intervalMatch[1]; $interval++)
<span class="rounded-sm bg-plan" style="flex: {{ (int) $intervalMatch[2] }}"></span>@if($interval < (int) $intervalMatch[1] - 1)<span class="rounded-sm bg-plan-bar" style="flex: {{ (int) $intervalMatch[3] }}"></span>@endif
@endfor
</span>
@endif
