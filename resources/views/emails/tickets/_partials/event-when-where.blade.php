@if($event && ($event->date_label || $event->location))
    @php
        $timeLine = $event->start_time
            ? $event->start_time
                .($event->end_time ? ' - '.$event->end_time : '')
                .($event->timezone ? ' ('.$event->timezone.')' : '')
            : null;
        $venueLine = $event->location.($event->hall ? ' · '.$event->hall : '');
        $whereTopMargin = $event->date_label ? '14' : '0';
    @endphp
    <div style="background:#ffffff;border:1px solid #e4e4e7;border-radius:12px;padding:16px;margin:0 0 20px;">
        @if($event->date_label)
            <p style="font-size:13px;color:#71717a;margin:0 0 2px;">When</p>
            <p style="font-size:15px;font-weight:600;margin:0;">{{ $event->date_label }}</p>
            @if($timeLine)
                <p style="font-size:13px;color:#3f3f46;margin:4px 0 0;">{{ $timeLine }}</p>
            @endif
        @endif

        @if($event->location)
            <p style="font-size:13px;color:#71717a;margin:{{ $whereTopMargin }}px 0 2px;">Where</p>
            <p style="font-size:15px;font-weight:600;margin:0;">{{ $venueLine }}</p>
            @if($event->location_link)
                <a href="{{ $event->location_link }}" style="display:inline-block;margin-top:8px;font-size:13px;color:#18181b;text-decoration:underline;">Get directions &rarr;</a>
            @endif
        @endif
    </div>
@endif
