<textarea
    x-data
    x-init="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
    x-on:input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
    {{ $attributes->merge(['class' => 'w-full border border-field bg-surface rounded-xl px-3 py-2.5 text-base text-ink placeholder:text-muted focus:outline-none focus:border-ink focus:ring-1 focus:ring-ink resize-none overflow-hidden']) }}
>{{ $slot }}</textarea>
