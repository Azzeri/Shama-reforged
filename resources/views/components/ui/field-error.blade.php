@props(['name'])

@error($name)
<p class="mt-1.5 text-[13px] text-terracotta">{{ $message }}</p>
@enderror
