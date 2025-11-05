<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $ogTitle }}</title>

    {{-- OpenGraph Meta Tags --}}
    <meta property="og:title" content="{{ $ogTitle }}" />
    @if($ogDescription)
        <meta property="og:description" content="{{ $ogDescription }}" />
    @endif
    @if($ogImage)
        <meta property="og:image" content="{{ $ogImage }}" />
    @endif
    <meta property="og:type" content="{{ $ogType }}" />
    <meta property="og:url" content="{{ url()->current() }}" />

    {{-- Twitter Card Meta Tags --}}
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $ogTitle }}" />
    @if($ogDescription)
        <meta name="twitter:description" content="{{ $ogDescription }}" />
    @endif
    @if($ogImage)
        <meta name="twitter:image" content="{{ $ogImage }}" />
    @endif

    {{-- Auto Redirect via Meta Refresh (fallback) --}}
    <meta http-equiv="refresh" content="0;url={{ $destinationUrl }}" />

    {{-- Styles for Loading State --}}
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
        }

        .container {
            text-align: center;
            padding: 2rem;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 1.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        p {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 1.5rem;
        }

        .fallback-link {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 8px;
            color: #ffffff;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .fallback-link:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.6);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="spinner"></div>
        <h1>Redirecting...</h1>
        <p>Taking you to your destination</p>
        <a href="{{ $destinationUrl }}" class="fallback-link">Click here if not redirected</a>
    </div>

    {{-- JavaScript Redirect (primary method) --}}
    <script>
        // Immediate redirect via JavaScript
        window.location.href = "{{ $destinationUrl }}";
    </script>
</body>
</html>
