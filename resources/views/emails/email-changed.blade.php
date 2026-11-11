<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 560px; margin: 0 auto; padding: 20px;">
    <p>{{ __('Hello :name,', ['name' => $name]) }}</p>
    <p>{{ __('The email address of your :app account was just changed to :email. Your other sessions were signed out.', ['app' => config('app.name'), 'email' => $maskedEmail]) }}</p>
    <p>{{ __('If this was you, there is nothing to do. If it was not, someone else may have access to your account: reset your password at :url as soon as you can. The reset link goes to the address that is now on the account, so if you cannot receive it, write to us from this address.', ['url' => route('password.request')]) }}</p>
    <p>{{ config('app.name') }}</p>
</body>
</html>
