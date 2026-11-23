{{-- Renders a stored UTC timestamp in the application's display timezone
     (config('app.display_timezone')). Storage stays UTC; only presentation
     is converted, so the same value can still be exported in raw form. --}}
@props(['at', 'format' => 'M d, Y H:i', 'fallback' => '-'])

{{ $at?->copy()->timezone(config('app.display_timezone'))?->format($format) ?? $fallback }}
