<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Document' }}</title>
    <style>
        @page { margin: 30px 35px; }
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; }
        .header { display: table; width: 100%; margin-bottom: 24px; }
        .header-left, .header-right { display: table-cell; vertical-align: top; }
        .header-right { text-align: right; }
        .brand-name { font-size: 18px; font-weight: 600; letter-spacing: -0.02em; }
        .doc-title { font-size: 22px; font-weight: 600; letter-spacing: -0.02em; }
        .doc-meta { font-size: 10px; color: #6b7280; margin-top: 4px; }
        .section-title { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin: 18px 0 6px; }
        .info-grid { display: table; width: 100%; }
        .info-cell { display: table-cell; width: 50%; vertical-align: top; padding-right: 12px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th { background: #f3f4f6; text-align: left; padding: 8px 10px; font-size: 10px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em; color: #4b5563; }
        table.items td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .text-right { text-align: right; }
        .totals { width: 280px; float: right; margin-top: 12px; }
        .totals .row { display: table; width: 100%; padding: 4px 0; }
        .totals .label { display: table-cell; color: #6b7280; }
        .totals .value { display: table-cell; text-align: right; }
        .totals .grand { font-size: 14px; font-weight: 600; border-top: 2px solid #1f2937; padding-top: 8px; margin-top: 4px; }
        .footer-note { margin-top: 40px; font-size: 10px; color: #6b7280; clear: both; }
        .stamp { float: right; color: #16a34a; border: 3px solid #16a34a; padding: 6px 12px; font-size: 18px; font-weight: 700; letter-spacing: 0.05em; transform: rotate(-8deg); }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
