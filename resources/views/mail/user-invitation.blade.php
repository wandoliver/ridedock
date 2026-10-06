<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<body>
    <h1>{{ __('accounts.mail_subject') }}</h1>
    <p>{{ __('accounts.mail_intro') }}</p>
    <p><a href="{{ $activationUrl }}">{{ __('accounts.activate') }}</a></p>
    <p>{{ __('accounts.mail_expiry') }}</p>
</body>
</html>
