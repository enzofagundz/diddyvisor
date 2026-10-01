@props([
    'image',
    'alt' => '',
    'heading',
    'description' => null,
])

<div class="dv-empty">
    <img class="dv-empty-art" src="{{ asset($image) }}" alt="{{ $alt }}" width="200" height="200">
    <p class="dv-empty-heading">{{ $heading }}</p>
    @if ($description)
        <p class="dv-empty-text">{{ $description }}</p>
    @endif
</div>
