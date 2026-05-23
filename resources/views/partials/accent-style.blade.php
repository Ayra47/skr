@auth
@php
    $accentColor = auth()->user()->profileSetting?->accent_color ?? '#5bbeff';
    $theme = auth()->user()->profileSetting?->theme ?? 'dark';
@endphp
<script>document.documentElement.setAttribute('data-theme', '{{ $theme }}');</script>
<style>:root { --accent: {{ $accentColor }}; }</style>
@endauth
