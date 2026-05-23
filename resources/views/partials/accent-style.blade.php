@auth
@php $accentColor = auth()->user()->profileSetting?->accent_color ?? '#5bbeff'; @endphp
<style>:root { --accent: {{ $accentColor }}; }</style>
@endauth
