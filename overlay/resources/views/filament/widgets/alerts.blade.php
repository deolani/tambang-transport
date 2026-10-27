<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-bell-alert" icon-color="warning">
        <x-slot name="heading">Peringatan & tindakan</x-slot>

        @if (empty($alerts))
            <div style="display:flex;align-items:center;gap:.6rem;opacity:.8">
                <x-filament::icon icon="heroicon-o-check-circle" style="width:1.4rem;height:1.4rem;color:rgb(16,185,129)" />
                <span>Semua beres, tidak ada yang perlu ditindaklanjuti.</span>
            </div>
        @else
            <ul style="display:flex;flex-direction:column;gap:.5rem;margin:0;padding:0;list-style:none">
                @foreach ($alerts as [$color, $icon, $text, $url])
                    @php
                        $rgb = match ($color) {
                            'danger' => '225,29,72',
                            'warning' => '234,88,12',
                            default => '37,99,201',
                        };
                    @endphp
                    <li style="display:flex;align-items:center;gap:.75rem;padding:.7rem .85rem;border-radius:.6rem;
                               background:rgba({{ $rgb }},.08);border-left:4px solid rgb({{ $rgb }})">
                        <x-filament::icon :icon="$icon" style="width:1.3rem;height:1.3rem;flex-shrink:0;color:rgb({{ $rgb }})" />
                        <span style="flex:1;line-height:1.35">{{ $text }}</span>
                        @if ($url)
                            <x-filament::link :href="$url" size="sm" icon="heroicon-m-arrow-right" icon-position="after">
                                Buka
                            </x-filament::link>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
