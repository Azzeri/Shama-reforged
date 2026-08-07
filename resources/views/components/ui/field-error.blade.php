@props(['name'])

@error($name)
<p class="mt-1.5 font-manrope text-xs text-terracotta-dark">{{ $message }}</p>
@enderror
