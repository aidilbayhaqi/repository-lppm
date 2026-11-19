@props(['subtitle' => 'Repositori akademik · LPPM'])
<span class="inline-flex min-w-0 items-center gap-2.5 align-middle">
    <span class="grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-white p-1 ring-1 ring-black/10 sm:h-11 sm:w-11">
        <img src="{{ asset('images/umiba-emblem.png') }}" alt="" aria-hidden="true"
            width="44" height="44" class="block h-full w-full object-contain" decoding="async">
    </span>
    <span class="min-w-0 text-left">
        <span class="block whitespace-nowrap text-[16px] font-bold leading-tight tracking-tight text-[var(--ink)]">
            UMIBA <span class="font-medium text-[var(--muted)]">LPPM</span>
        </span>
        <span class="mt-0.5 block max-w-[150px] text-[10px] leading-snug text-[var(--muted)]">{{ $subtitle }}</span>
    </span>
</span>
