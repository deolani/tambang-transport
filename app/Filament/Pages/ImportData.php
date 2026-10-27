<?php

namespace App\Filament\Pages;

use App\Imports\BaseImporter;
use App\Imports\ImporterRegistry;
use App\Imports\ImportRowException;
use App\Imports\SpreadsheetReader;
use App\Imports\TemplateWriter;
use App\Models\ImportLog;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Halaman upload data: pilih jenis data -> pilih file -> cek -> proses.
 */
class ImportData extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Operasional';

    protected static ?string $navigationLabel = 'Upload / Import Data';

    protected static ?string $title = 'Upload / Import Data';

    protected static ?string $slug = 'import-data';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.import-data';

    public ?array $data = [];

    /** Hasil "Cek data" / "Proses import" untuk ditampilkan. */
    public ?array $result = null;

    /** 'check' atau 'done' */
    public ?string $stage = null;

    /** Path file yang sudah disimpan dan nama aslinya. */
    public ?string $storedPath = null;

    public ?string $originalName = null;

    public static function canAccess(): bool
    {
        return ImporterRegistry::optionsFor(auth()->user()) !== [];
    }

    public function mount(): void
    {
        $this->form->fill(['skip_errors' => true]);
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Section::make('1. Pilih jenis data')->icon('heroicon-o-rectangle-stack')->schema([
                Forms\Components\Select::make('type')->label('Jenis data yang akan di-upload')
                    ->options(fn () => ImporterRegistry::optionsFor(auth()->user()))
                    ->required()->live()->native(false)
                    ->afterStateUpdated(fn () => $this->resetResult())
                    ->helperText('Untuk import pertama, ikuti urutan nomor: master data dulu, baru trip.'),
                Forms\Components\Placeholder::make('columns_info')->hiddenLabel()
                    ->visible(fn (Get $get) => filled($get('type')))
                    ->content(fn (Get $get) => $this->columnsHtml($get('type'))),
            ]),
            Forms\Components\Section::make('2. Pilih file')->icon('heroicon-o-document-arrow-up')->schema([
                Forms\Components\FileUpload::make('file')->label('File Excel (.xlsx) atau CSV')
                    ->disk('local')->directory('imports')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel',
                    ])
                    ->maxSize(10240)
                    ->storeFileNamesIn('original_name')
                    ->required()->live()
                    ->afterStateUpdated(fn () => $this->resetResult())
                    ->helperText('Baris pertama harus berisi nama kolom. Maksimal 5.000 baris, 10 MB.'),
                Forms\Components\Hidden::make('original_name'),
                Forms\Components\Toggle::make('skip_errors')->label('Lewati baris yang error, simpan baris yang benar')
                    ->helperText('Bila dimatikan: satu baris error = tidak ada data yang disimpan.')
                    ->default(true),
            ]),
        ]);
    }

    protected function columnsHtml(?string $type): HtmlString
    {
        $class = ImporterRegistry::find($type);
        if (! $class) {
            return new HtmlString('');
        }
        $rows = '';
        foreach ($class::columns() as [$label, $required, $example, $hint]) {
            $rows .= '<tr style="border-top:1px solid rgba(120,113,108,.2)">'
                .'<td style="padding:6px 10px;font-weight:600">'.e($label).($required ? ' <span style="color:#e11d48">*</span>' : '').'</td>'
                .'<td style="padding:6px 10px;opacity:.8">'.e((string) $example).'</td>'
                .'<td style="padding:6px 10px;opacity:.7">'.e($hint).'</td></tr>';
        }

        return new HtmlString(
            '<p style="margin-bottom:8px">'.e($class::description()).'</p>'
            .'<div style="overflow-x:auto"><table style="width:100%;font-size:.875rem;border-collapse:collapse">'
            .'<thead><tr style="text-align:left;opacity:.7"><th style="padding:6px 10px">Kolom</th><th style="padding:6px 10px">Contoh</th>'
            .'<th style="padding:6px 10px">Keterangan</th></tr></thead><tbody>'.$rows.'</tbody></table></div>'
            .'<p style="margin-top:8px;font-size:.8rem;opacity:.7"><span style="color:#e11d48">*</span> wajib diisi. '
            .'Angka boleh format Indonesia (46,64 atau 61.200). Tanggal: 2026-10-03 atau 03/10/2026.</p>'
        );
    }

    public function resetResult(): void
    {
        $this->result = null;
        $this->stage = null;
    }

    protected function importer(): ?BaseImporter
    {
        $class = ImporterRegistry::find($this->data['type'] ?? null);
        if (! $class || ! ImporterRegistry::allowed(auth()->user(), $class)) {
            return null;
        }

        return new $class;
    }

    // ------------------------------------------------------------- Aksi

    public function downloadTemplate()
    {
        $class = ImporterRegistry::find($this->data['type'] ?? null);
        if (! $class) {
            Notification::make()->warning()->title('Pilih jenis data dulu')->send();

            return null;
        }

        try {
            return TemplateWriter::download($class);
        } catch (Throwable $e) {
            report($e);
            Notification::make()->danger()->title('Template gagal dibuat')->body($e->getMessage())->send();

            return null;
        }
    }

    /** Baca file & jalankan simulasi (semua perubahan dibatalkan). */
    public function check(): void
    {
        $state = $this->form->getState();
        $this->storedPath = is_array($state['file']) ? (array_values($state['file'])[0] ?? null) : $state['file'];
        $this->originalName = is_array($state['original_name'] ?? null)
            ? (array_values($state['original_name'])[0] ?? null)
            : ($state['original_name'] ?? basename((string) $this->storedPath));

        $this->runImport(dryRun: true);
    }

    /** Simpan sungguhan. */
    public function process(): void
    {
        if ($this->stage !== 'check' || ! $this->storedPath) {
            Notification::make()->warning()->title('Klik "Cek data" dulu')->send();

            return;
        }
        $this->runImport(dryRun: false);
    }

    protected function runImport(bool $dryRun): void
    {
        $importer = $this->importer();
        if (! $importer) {
            Notification::make()->danger()->title('Jenis data tidak valid / tidak diizinkan')->send();

            return;
        }

        try {
            $path = Storage::disk('local')->path($this->storedPath);
            $rows = SpreadsheetReader::read($path, $this->originalName);

            $missing = $importer::missingRequiredHeaders($rows);
            if ($missing) {
                throw new ImportRowException('Kolom wajib tidak ditemukan di baris header: '.implode(', ', $missing)
                    .'. Unduh template untuk melihat nama kolom yang benar.');
            }

            @set_time_limit(300);
            $res = $importer->run($rows, $dryRun, (bool) ($this->data['skip_errors'] ?? true));
        } catch (Throwable $e) {
            if (! $e instanceof ImportRowException) {
                report($e);
            }
            $this->result = null;
            $this->stage = null;
            Notification::make()->danger()->title('File tidak bisa diproses')->body($e->getMessage())->persistent()->send();

            return;
        }

        $this->result = $res;

        if ($dryRun) {
            $this->stage = 'check';
            $ok = $res['created'] + $res['updated'];
            Notification::make()
                ->title("Cek selesai: {$ok} baris siap, {$res['failed']} error")
                ->{$res['failed'] ? 'warning' : 'success'}()
                ->send();

            return;
        }

        $saved = ($res['failed'] > 0 && ! ($this->data['skip_errors'] ?? true)) ? false : true;

        ImportLog::create([
            'type' => $importer::key(),
            'file_name' => $this->originalName ?? basename($this->storedPath),
            'total_rows' => $res['total'],
            'created_count' => $saved ? $res['created'] : 0,
            'updated_count' => $saved ? $res['updated'] : 0,
            'failed_count' => $res['failed'],
            'errors' => collect($res['rows'])->where('ok', false)->take(200)
                ->map(fn ($r) => ['line' => $r['line'], 'message' => $r['message']])->values()->all(),
            'user_id' => auth()->id(),
        ]);

        Storage::disk('local')->delete($this->storedPath);
        $this->storedPath = null;
        $this->stage = 'done';
        $this->form->fill(['type' => $this->data['type'], 'skip_errors' => $this->data['skip_errors'] ?? true]);

        if ($saved) {
            Notification::make()->success()
                ->title('Import selesai')
                ->body("{$res['created']} data baru, {$res['updated']} diperbarui, {$res['failed']} dilewati.")
                ->send();
        } else {
            Notification::make()->danger()
                ->title('Tidak ada data disimpan')
                ->body("Ada {$res['failed']} baris error dan opsi lewati error dimatikan.")
                ->send();
        }
    }

    public function getRecentLogs()
    {
        return ImportLog::with('user')->latest()->limit(10)->get();
    }
}
