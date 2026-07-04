<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { width: 1200px; height: 630px; overflow: hidden; }
    body {
        position: relative;
        font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
    }
    .bg {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .overlay {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 50%;
        background: linear-gradient(to top, rgba(0, 0, 0, .6), rgba(0, 0, 0, 0));
    }
    .title {
        position: absolute;
        left: 72px;
        right: 72px;
        bottom: 64px;
        color: #fff;
        font-size: 62px;
        line-height: 1.15;
        font-weight: 500;
        letter-spacing: -0.04em;
        text-shadow: 0 2px 12px rgba(0, 0, 0, .45);
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>
</head>
<body>
    <img class="bg" src="{{ $imageDataUri }}" alt="">
    <div class="overlay"></div>
    <h1 class="title">{{ $title }}</h1>
</body>
</html>
