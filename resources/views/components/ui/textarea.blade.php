<textarea
    {{ $attributes->merge(['class' => 'w-full border-[1.5px] border-ink/25 bg-white rounded-2xl px-3.5 py-[13px] font-manrope text-base sm:text-sm text-ink placeholder:text-ink/35 focus:outline-none focus:ring-2 focus:ring-terracotta/30 resize-none']) }}
>{{ $slot }}</textarea>
