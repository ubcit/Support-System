<span @if ($kind === 'inbox') wire:poll.10s @endif>
    @if ($count > 0)
        <span
            @class([
                'absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-bold leading-none text-white' => $placement === 'rail',
                'shrink-0 rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-bold text-white' => $placement === 'mobile',
            ])
            aria-label="{{ $count }} {{ $label }}"
        >
            {{ $count > 9 ? '9+' : $count }}
        </span>
    @endif
</span>
