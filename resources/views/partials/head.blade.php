<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<meta name="mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-title" content="Shama" />
<meta name="theme-color" content="#FBF7F0" />
<meta name="format-detection" content="telephone=no" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Shama') : config('app.name', 'Shama') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/manifest.webmanifest">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
