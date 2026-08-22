<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">

        {{-- Pose le thème avant le premier rendu pour éviter le flash ; app.ts prend ensuite le relais.
             La clé et la règle de repli doivent rester celles de resources/js/lib/theme.ts. --}}
        <script>
            (function () {
                var stored = null;

                try {
                    stored = localStorage.getItem('argent-theme');
                } catch (error) {
                    stored = null;
                }

                var dark = stored === 'dark'
                    || (stored !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);

                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        @include('pwa.meta-tags')

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body>
        @inertia

        {{-- Hors de l'application Inertia : les pages sont des racines indépendantes, sans layout partagé. --}}
        <div id="pwa-banner"></div>
    </body>
</html>
