@foreach (['status', 'analysis_error'] as $message)
@if (session($message))<div class="m-4 rounded-lg bg-surface p-3 text-sm" role="{{ $message === 'status' ? 'status' : 'alert' }}">{{ session($message) }}</div>@endif
@endforeach
@if ($errors->any())<div class="m-4 rounded-lg bg-surface p-3 text-sm" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
