@props(['name', 'size' => 20])
<svg {{ $attributes->class(['icon'])->merge(['width' => $size, 'height' => $size]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('book')
            <path d="M12 5.5C8.5 3.5 5.5 3.5 3 4.5v15c3-1 6-1 9 1 3-2 6-2 9-1v-15c-2.5-1-5.5-1-9 1Z"/><path d="M12 5.5v15M6 8h3M6 11h3M15 8h3M15 11h3"/>
            @break
        @case('document')
            <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>
            @break
        @case('shield')
            <path d="m12 3 8 3v6c0 4-3.5 7.5-8 9-4.5-1.5-8-5-8-9V6Z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>
            @break
        @case('grid')
            <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
            @break
        @case('upload')
            <path d="M12 16V3m-4 4 4-4 4 4M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/>
            @break
        @case('users')
            <circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M21 21v-2a6 6 0 0 0-4-5.5"/>
            @break
        @case('layers')
            <path d="m12 3 10 5-10 5L2 8Zm-10 9 10 5 10-5M2 16l10 5 10-5"/>
            @break
        @case('globe')
            <circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/>
            @break
        @case('arrow-right')
            <path d="M4 12h16m-6-6 6 6-6 6"/>
            @break
        @case('arrow-up-right')
            <path d="M6 18 18 6M6 6h12v12"/>
            @break
        @case('search')
            <circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>
            @break
        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16"/>
            @break
        @case('close')
            <path d="m6 6 12 12M6 18 18 6"/>
            @break
        @case('logout')
            <path d="M9 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4M9 12h12m-4-4 4 4-4 4"/>
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14"/>
            @break
        @case('filter')
            <path d="M4 7h16M7 12h10M10 17h4"/>
            @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6"/>
            @break
    @endswitch
</svg>
