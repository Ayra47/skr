{{--
    Standard sidebar: header / body / footer slots.
    If no `footer` slot is provided, the default <x-app-sidebar-footer /> is rendered.
--}}
<aside class="app-sidebar">
    @isset($header)
        <div class="app-sidebar-header">
            {{ $header }}
        </div>
    @endisset

    @isset($body)
        <div class="app-sidebar-body">
            {{ $body }}
        </div>
    @endisset

    <div class="app-sidebar-footer">
        {{ $footer ?? '' }}
        @if(! isset($footer))
            <x-app-sidebar-footer />
        @endif
    </div>
</aside>
