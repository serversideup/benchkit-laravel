<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        @vite('resources/js/app.js')
        @vite('resources/css/app.css')
        
        @inertiaHead
    </head>
    
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>