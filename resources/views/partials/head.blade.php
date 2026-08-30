<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<meta name="description" content="Building Bridges Across Cultures — a social platform where identity comes first and curiosity is the reason to connect.">
<meta property="og:title" content="{{ config('app.name', 'Laravel') }} — Building Bridges Across Cultures">
<meta property="og:description" content="A social platform where identity comes first and curiosity is the reason to connect.">
<meta property="og:image" content="{{ asset('social-preview-1200x630.png') }}">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="{{ asset('social-preview-1200x630.png') }}">

<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="48x48" href="/favicon-48x48.png">
<link rel="apple-touch-icon" href="/apple-touch-icon-180x180.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
