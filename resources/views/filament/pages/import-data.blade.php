<x-filament-panels::page>
    <form wire:submit="check">
        {{ $this->form }}

        <div style="display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1.25rem">
            <x-filament::button type="button" color="gray" icon="heroicon-o-arrow-down-tray"
                wire:click="downloadTemplate" :disabled="blank($this->data['type'] ?? null)">
                Unduh template
            </x-filament::button>

            <x-filament::button type="submit" icon="heroicon-o-magnifying-glass" wire:target="check,data.file">
                Cek data
            </x-filament::button>

            @if ($stage === 'check' && $result && ($result['created'] + $result['updated']) > 0)
                <x-filament::button type="button" color="success" icon="heroicon-o-cloud-arrow-up"
                    wire:click="process" wire:target="process"
                    wire:confirm="Simpan {{ $result['created'] + $result['updated'] }} baris ke database?">
                    Proses import ({{ $result['created'] + $result['updated'] }} baris)
                </x-filament::button>
            @endif
        </div>
    </form>

    @if ($result)
        @php
            $ok = $result['created'] + $result['updated'];
            $tiles = [
                ['Total baris', $result['total'], '120,113,108'],
                [$stage === 'done' ? 'Data baru' : 'Akan ditambah', $result['created'], '37,99,201'],
                [$stage === 'done' ? 'Diperbarui' : 'Akan diperbarui', $result['updated'], '26,154,138'],
                ['Error', $result['failed'], '225,29,72'],
            ];
            $keys = $result['rows'] ? array_keys($result['rows'][0]['values']) : [];
        @endphp

        <x-filament::section :icon="$stage === 'done' ? 'heroicon-o-check-circle' : 'heroicon-o-eye'"
            :icon-color="$stage === 'done' ? 'success' : 'info'">
            <x-slot name="heading">
                {{ $stage === 'done' ? 'Hasil import' : 'Pratinjau — belum ada yang disimpan' }}
            </x-slot>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.75rem;margin-bottom:1rem">
                @foreach ($tiles as [$label, $value, $rgb])
                    <div style="padding:.85rem 1rem;border-radius:.75rem;background:rgba({{ $rgb }},.09);border-left:4px solid rgb({{ $rgb }})">
                        <div style="font-size:.8rem;opacity:.75">{{ $label }}</div>
                        <div style="font-size:1.6rem;font-weight:700">{{ number_format($value, 0, ',', '.') }}</div>
                    </div>
                @endforeach
            </div>

            @if ($stage === 'check' && $result['failed'] > 0)
                <p style="margin-bottom:.75rem;color:rgb(225,29,72)">
                    Perbaiki baris yang error di file lalu upload ulang, atau lanjutkan — baris error akan dilewati
                    bila opsi "lewati baris yang error" aktif.
                </p>
            @endif

            <div style="overflow-x:auto;max-height:28rem;overflow-y:auto;border:1px solid rgba(120,113,108,.2);border-radius:.6rem">
                <table style="width:100%;font-size:.85rem;border-collapse:collapse">
                    <thead style="position:sticky;top:0;background:rgba(120,113,108,.12)">
                        <tr style="text-align:left">
                            <th style="padding:8px 10px">Baris</th>
                            <th style="padding:8px 10px">Status</th>
                            @foreach ($keys as $k)
                                <th style="padding:8px 10px;white-space:nowrap">{{ str_replace('_', ' ', ucfirst($k)) }}</th>
                            @endforeach
                            <th style="padding:8px 10px">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (collect($result['rows'])->sortBy('ok')->take(300) as $row)
                            <tr style="border-top:1px solid rgba(120,113,108,.15)">
                                <td style="padding:6px 10px">{{ $row['line'] }}</td>
                                <td style="padding:6px 10px">
                                    <x-filament::badge :color="$row['ok'] ? 'success' : 'danger'">
                                        {{ $row['ok'] ? 'OK' : 'Error' }}
                                    </x-filament::badge>
                                </td>
                                @foreach ($keys as $k)
                                    <td style="padding:6px 10px;white-space:nowrap">{{ $row['values'][$k] ?? '' }}</td>
                                @endforeach
                                <td style="padding:6px 10px;{{ $row['ok'] ? 'opacity:.7' : 'color:rgb(225,29,72)' }}">{{ $row['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($result['rows']) > 300)
                <p style="margin-top:.5rem;font-size:.8rem;opacity:.7">Menampilkan 300 baris pertama (error di urutan teratas).</p>
            @endif
        </x-filament::section>
    @endif

    @php($logs = $this->getRecentLogs())
    @if ($logs->isNotEmpty())
        <x-filament::section icon="heroicon-o-clock" collapsible>
            <x-slot name="heading">Riwayat import</x-slot>
            <div style="overflow-x:auto">
                <table style="width:100%;font-size:.85rem;border-collapse:collapse">
                    <thead>
                        <tr style="text-align:left;opacity:.7">
                            <th style="padding:6px 10px">Waktu</th>
                            <th style="padding:6px 10px">Jenis</th>
                            <th style="padding:6px 10px">File</th>
                            <th style="padding:6px 10px;text-align:right">Baru</th>
                            <th style="padding:6px 10px;text-align:right">Update</th>
                            <th style="padding:6px 10px;text-align:right">Error</th>
                            <th style="padding:6px 10px">Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr style="border-top:1px solid rgba(120,113,108,.15)">
                                <td style="padding:6px 10px;white-space:nowrap">{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td style="padding:6px 10px">{{ $log->type_label }}</td>
                                <td style="padding:6px 10px">{{ \Illuminate\Support\Str::limit($log->file_name, 40) }}</td>
                                <td style="padding:6px 10px;text-align:right">{{ $log->created_count }}</td>
                                <td style="padding:6px 10px;text-align:right">{{ $log->updated_count }}</td>
                                <td style="padding:6px 10px;text-align:right;{{ $log->failed_count ? 'color:rgb(225,29,72);font-weight:600' : '' }}">{{ $log->failed_count }}</td>
                                <td style="padding:6px 10px">{{ $log->user?->name }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
