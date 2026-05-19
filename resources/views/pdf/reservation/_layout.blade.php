<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Document' }}</title>
    {{-- Inline the compiled Tailwind CSS so Browsershot does not need an extra
         HTTP round-trip during render. Avoids `waitUntilNetworkIdle` stalls
         when Vite HMR is not running. --}}
    @php
        $manifestPath = public_path('build/manifest.json');
        $pdfCss = null;
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            $assetPath = $manifest['resources/css/pdf.css']['file'] ?? null;
            if ($assetPath && file_exists(public_path('build/'.$assetPath))) {
                $pdfCss = file_get_contents(public_path('build/'.$assetPath));
            }
        }
    @endphp
    @if ($pdfCss)
        <style>{!! $pdfCss !!}</style>
    @endif
</head>
<body class="font-sans text-black tracking-tight text-[12px] leading-normal flex flex-col" style="min-height: 1000px;">
    @yield('content')
</body>
</html>
