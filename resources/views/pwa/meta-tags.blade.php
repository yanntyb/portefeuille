<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="{{ config('pwa.theme_color') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ config('pwa.short_name') }}">
@foreach(config('pwa.icons', []) as $icon)
<link rel="apple-touch-icon" sizes="{{ $icon['sizes'] }}" href="{{ $icon['src'] }}">
@endforeach
