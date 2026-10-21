<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <meta name="description" content="{{ $description ?? 'Install the app and its Podman services on this host.' }}">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ $title ?? config('app.name') }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen bg-base-200 text-base-content antialiased">
        {{ $slot }}

        @livewireScripts
    </body>
</html>
