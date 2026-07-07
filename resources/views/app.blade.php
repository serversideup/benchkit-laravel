<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
        <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
        <link rel="shortcut icon" href="/favicon.ico" />
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
        <meta name="apple-mobile-web-app-title" content="BenchKit" />
        <link rel="manifest" href="/site.webmanifest" />

        @vite('resources/js/app.js')
        @vite('resources/css/app.css')
        
        @inertiaHead
    </head>
    
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>