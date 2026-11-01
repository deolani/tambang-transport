<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\RelationManagers;
use App\Models\Customer;
use App\Models\Invoice;
use App\Support\HaulingMetrics as M;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class InvoiceResource extends Resource
{
    use HasRoleAccess;

    public static function permissionPrefix(): string
    {
        return 'invoices';
    }

    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Keuangan';

    protected static ?string $modelLabel = 'Invoice';

    protected static ?string $pluralModelLabel = 'Invoice';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'number';

    /** Invoice tidak diedit setelah terbit; batalkan lalu buat ulang. */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /** Invoice tidak pernah dihapus agar nomor dan jejaknya tetap ada; gunakan aksi Batalkan. */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canCancel(Invoice $record): bool
    {
        return static::allows('cancel') && $record->status !== 'batal' && (float) $record->paid_amount <= 0;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->columns(3)->schema([
            Forms\Components\Section::make('Buat invoice dari trip VERIFIED')->columnSpan(['default' => 3, 'lg' => 2])
                ->columns(2)->schema([
                    Forms\Components\Select::make('customer_id')->label('Customer')
                        ->relationship('customer', 'name')->searchable()->preload()->required()->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            foreach (Invoice::defaultTaxes(Customer::find($state)) as $field => $value) {
                                $set($field, $value);
                            }
                        })
                        ->columnSpanFull(),
                    Forms\Components\DatePicker::make('period_from')->label('Periode dari')->required()->native(false)
                        ->default(now()->startOfMonth())->live(),
                    Forms\Components\DatePicker::make('period_to')->label('Periode sampai')->required()->native(false)
                        ->default(now())->afterOrEqual('period_from')->live()
                        ->rules([
                            fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                if ($get('customer_id') && ! Invoice::billableTrips((int) $get('customer_id'), $get('period_from'), $value)->exists()) {
                                    $fail('Tidak ada trip VERIFIED yang belum ditagih pada periode ini.');
                                }
                            },
                        ]),
                    Forms\Components\DatePicker::make('issue_date')->label('Tanggal invoice')->required()->native(false)->default(now()),
                    Forms\Components\TextInput::make('ppn_percent')->label('PPN')->numeric()->suffix('%')
                        ->default(fn () => Invoice::defaultTaxes(null)['ppn_percent'])->live(onBlur: true)
                        ->helperText('Terisi otomatis; ubah bila ketentuan berbeda.'),
                    Forms\Components\TextInput::make('pph_percent')->label('PPh 23 (dipotong customer)')->numeric()->suffix('%')
                        ->default(0)->live(onBlur: true)
                        ->helperText('Terisi otomatis untuk customer PKP.'),
                    Forms\Components\Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Ringkasan')->icon('heroicon-o-calculator')
                ->columnSpan(['default' => 3, 'lg' => 1])
                ->schema([
                    Forms\Components\Placeholder::make('summary')->hiddenLabel()
                        ->content(fn (Get $get) => static::summaryHtml($get)),
                ]),
        ]);
    }

    protected static function summaryHtml(Get $get): HtmlString
    {
        if (! $get('customer_id') || ! $get('period_from') || ! $get('period_to')) {
            return new HtmlString('<p style="opacity:.7">Pilih customer dan periode.</p>');
        }
        $q = Invoice::billableTrips((int) $get('customer_id'), $get('period_from'), $get('period_to'));
        $count = (clone $q)->count();
        $ton = (float) (clone $q)->sum('tonnage');
        $sub = (float) (clone $q)->sum('amount');
        $ppn = Invoice::tax($sub, (float) $get('ppn_percent'));
        $pph = Invoice::tax($sub, (float) $get('pph_percent'));

        $row = fn ($l, $v, $b = false) => '<div style="display:flex;justify-content:space-between;padding:5px 0;'
            .($b ? 'font-weight:700;border-top:1px solid rgba(120,113,108,.3);margin-top:6px;padding-top:9px' : '').'"><span>'
            .e($l).'</span><span>'.e($v).'</span></div>';

        return new HtmlString(
            $row('Jumlah trip', $count.' trip')
            .$row('Total tonase', M::num($ton).' t')
            .$row('DPP', M::rupiah($sub))
            .$row('PPN', M::rupiah($ppn))
            .$row('PPh 23', '− '.M::rupiah($pph))
            .$row('Total tagihan', M::rupiah($sub + $ppn - $pph), true)
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('issue_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('No invoice')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('customer.name')->label('Customer')->wrap()->searchable(),
                Tables\Columns\TextColumn::make('period_from')->label('Periode')
                    ->formatStateUsing(fn (Invoice $record) => $record->period_from->format('d M').' – '.$record->period_to->format('d M Y')),
                Tables\Columns\TextColumn::make('trip_count')->label('Trip')->alignEnd()->toggleable(),
                Tables\Columns\TextColumn::make('total')->label('Total')->money('IDR', locale: 'id')->alignEnd()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total (tanpa batal)')->money('IDR', locale: 'id')
                        ->query(fn ($query) => $query->where('status', '!=', 'batal'))),
                Tables\Columns\TextColumn::make('paid_amount')->label('Dibayar')->money('IDR', locale: 'id')->alignEnd()->toggleable(),
                Tables\Columns\TextColumn::make('due_date')->label('Jatuh tempo')->date('d M Y')
                    ->color(fn (Invoice $record) => in_array($record->status, ['belum_lunas', 'sebagian'], true) && $record->due_date->isPast() ? 'danger' : null),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => Invoice::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => Invoice::STATUS_COLORS[$state] ?? 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(Invoice::STATUSES),
                Tables\Filters\SelectFilter::make('customer_id')->label('Customer')->relationship('customer', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                static::cancelAction(Tables\Actions\Action::make('cancel')),
            ]);
    }

    /** Aksi Batalkan, dipakai di tabel dan halaman detail. */
    public static function cancelAction($action)
    {
        return $action->label('Batalkan')->icon('heroicon-o-x-circle')->color('danger')
            ->visible(fn (Invoice $record) => static::canCancel($record))
            ->requiresConfirmation()
            ->modalHeading(fn (Invoice $record) => "Batalkan {$record->number}?")
            ->modalDescription('Trip di invoice ini kembali berstatus VERIFIED dan bisa ditagih ulang. Nomor invoice tetap tercatat sebagai Batal.')
            ->form([
                Forms\Components\TextInput::make('reason')->label('Alasan pembatalan')->required()->maxLength(255),
            ])
            ->action(function (Invoice $record, array $data) {
                $record->cancel($data['reason']);
                Notification::make()->success()->title("{$record->number} dibatalkan")->send();
            });
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->columns(['default' => 2, 'md' => 4])->schema([
                Infolists\Components\TextEntry::make('number')->label('No invoice')->weight('bold'),
                Infolists\Components\TextEntry::make('customer.name')->label('Customer'),
                Infolists\Components\TextEntry::make('issue_date')->label('Tanggal')->date('d M Y'),
                Infolists\Components\TextEntry::make('due_date')->label('Jatuh tempo')->date('d M Y'),
                Infolists\Components\TextEntry::make('trip_count')->label('Jumlah trip'),
                Infolists\Components\TextEntry::make('total_tonnage')->label('Tonase')->numeric(2, ',', '.')->suffix(' t'),
                Infolists\Components\TextEntry::make('subtotal')->label('DPP')->money('IDR', locale: 'id'),
                Infolists\Components\TextEntry::make('ppn_amount')->label('PPN')->money('IDR', locale: 'id'),
                Infolists\Components\TextEntry::make('pph_amount')->label('PPh 23')->money('IDR', locale: 'id'),
                Infolists\Components\TextEntry::make('total')->label('Total tagihan')->money('IDR', locale: 'id')->weight('bold'),
                Infolists\Components\TextEntry::make('paid_amount')->label('Sudah dibayar')->money('IDR', locale: 'id'),
                Infolists\Components\TextEntry::make('status')->badge()
                    ->formatStateUsing(fn ($state) => Invoice::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => Invoice::STATUS_COLORS[$state] ?? 'gray'),
            ]),
            Infolists\Components\Section::make('Pembatalan')->columns(['default' => 1, 'md' => 3])
                ->visible(fn (Invoice $record) => $record->status === 'batal')
                ->schema([
                    Infolists\Components\TextEntry::make('cancelled_at')->label('Dibatalkan')->dateTime('d M Y H:i'),
                    Infolists\Components\TextEntry::make('cancelledBy.name')->label('Oleh'),
                    Infolists\Components\TextEntry::make('cancel_reason')->label('Alasan'),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentsRelationManager::class,
            RelationManagers\TripsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }
}
