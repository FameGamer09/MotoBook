<button
    {{ $attributes->merge(['class' => 'rider-theme-toggle']) }}
    type="button"
    data-rider-theme-toggle
    aria-label="Switch to dark mode"
    aria-pressed="false"
>
    <span aria-hidden="true">
        <svg class="rider-theme-icon rider-theme-icon-moon" viewBox="0 0 24 24" fill="none" focusable="false"><path d="M20.2 15.4A8.7 8.7 0 0 1 8.6 3.8 8.8 8.8 0 1 0 20.2 15.4Z" fill="currentColor"/></svg>
        <svg class="rider-theme-icon rider-theme-icon-sun" viewBox="0 0 24 24" fill="none" focusable="false"><circle cx="12" cy="12" r="4" fill="currentColor"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    </span>
</button>
