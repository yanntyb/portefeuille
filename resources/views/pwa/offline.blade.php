<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">
        <title>Hors-ligne — {{ config('pwa.name') }}</title>
        <style>
            :root { color-scheme: light dark; }
            body {
                margin: 0;
                min-height: 100dvh;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 0.75rem;
                padding: 2rem;
                text-align: center;
                font-family: system-ui, -apple-system, sans-serif;
                background: Canvas;
                color: CanvasText;
            }
            h1 { margin: 0; font-size: 1.0625rem; font-weight: 700; }
            p { margin: 0; max-width: 32ch; font-size: 0.875rem; opacity: 0.7; }
        </style>
    </head>
    <body>
        <h1>Pas de connexion</h1>
        <p>Les données affichées proviennent du cache. Cette page n'a pas encore été consultée, elle n'est donc pas disponible hors-ligne.</p>
    </body>
</html>
