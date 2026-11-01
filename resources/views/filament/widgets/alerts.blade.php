<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Perlu ditindaklanjuti</x-slot>

        @if (empty($alerts))
            <p style="margin:0;color:rgb(21,128,61)">Semua beres, tidak ada yang perlu ditindaklanjuti.</p>
        @else
            <ul style="display:flex;flex-direction:column;margin:-.5rem 0;padding:0;list-style:none">
                @foreach ($alerts as [$color, $icon, $text, $url])
                    @php
                        $label = match ($color) {
                            'danger' => 'Penting',
                            'warning' => 'Perhatian',
                            default => 'Info',
                        };
                    @endphp
                    <li style="display:flex;flex-wrap:wrap;align-items:center;gap:.25rem 1rem;padding:.75rem 0;
                               {{ $loop->last ? '' : 'border-bottom:1px solid rgba(120,113,108,.18);' }}">
                        <span style="width:6rem;flex-shrink:0">
                            <x-filament::badge :color="$color">{{ $label }}</x-filament::badge>
                        </span>
                        <span style="flex:1 1 18rem;line-height:1.4">{{ $text }}</span>
                        @if ($url)
                            <x-filament::link :href="$url" size="sm">Buka</x-filament::link>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
