<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\TripResource\Pages;
use App\Models\Driver;
use App\Models\FuelPrice;
use App\Models\HaulRoute;
use App\Models\Tariff;
use App\Models\Trip;
use App\Support\HaulingMetrics as M;
use App\Support\Permissions;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class TripResource extends Resource
{
    use HasRoleAccess;

    public static function permissionPrefix(): string
    {
        return 'trips';
    }

    protected static ?string $model = Trip::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Operasional';

    protected static ?string $modelLabel = 'Trip';

    protected static ?string $pluralModelLabel = 'Trip / Ritase';

    protected static ?string $slug = 'trips';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'number';

    public static function canVerify(): bool
    {
        return static::allows('verify');
    }

    public static function canExport(): bool
    {
        return static::allows('export');
    }

    /** Draft: hak "edit". Verified: hak "edit_verified". Invoiced/paid: terkunci untuk semua. */
    public static function canEdit(Model $record): bool
    {
        return match ($record->status) {
            'draft' => static::allows('edit'),
            'verified' => static::allows('edit_verified'),
            default => false,
        };
    }

    public static function canDelete(Model $record): bool
    {
        return $record->status === 'draft' && static::allows('delete');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Trip::where('status', 'draft')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Trip belum diverifikasi';
    }

    public static function form(Form $form): Form
    {
        return $form->columns(3)->schema([
            Forms\Components\Section::make('Data trip')->columnSpan(['default' => 3, 'lg' => 2])->columns(2)->schema([
                Forms\Components\DatePicker::make('trip_date')->label('Tanggal')->default(now())
                    ->required()->native(false)->live(),
                Forms\Components\TextInput::make('ticket_number')->label('No tiket timbang')
                    ->placeholder('CE261003175907')->required()->unique(ignoreRecord: true)->maxLength(40),
                Forms\Components\Select::make('unit_id')->label('Unit')
                    ->relationship('unit', 'code', fn (Builder $query, ?Trip $record, Get $get) => $query
                        ->where(fn ($w) => $w->availableOn($get('trip_date'))->when($record, fn ($x) => $x->orWhere('id', $record->unit_id))))
                    ->helperText('Unit yang sedang breakdown pada tanggal trip tidak muncul.')
                    ->searchable()->preload()->required()->live()
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        if ($state && ! $get('driver_id')) {
                            $set('driver_id', Driver::where('default_unit_id', $state)->where('status', 'aktif')->value('id'));
                        }
                    }),
                Forms\Components\Select::make('driver_id')->label('Sopir')
                    ->relationship('driver', 'name', fn (Builder $query) => $query->where('status', 'aktif'))
                    ->searchable()->preload()->required()->live(),
                Forms\Components\Select::make('customer_id')->label('Customer')
                    ->relationship('customer', 'name', fn (Builder $query) => $query->where('status', 'aktif'))
                    ->searchable()->preload()->required()->live(),
                Forms\Components\Select::make('haul_route_id')->label('Rute')
                    ->relationship('haulRoute', 'code', fn (Builder $query) => $query->where('status', 'aktif'))
                    ->searchable()->preload()->required()->live()
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                            if (! Tariff::activeFor($get('customer_id'), $value, $get('trip_date'))) {
                                $fail('Tarif customer + rute ini belum ada untuk tanggal trip. Isi di Master Data > Tarif.');
                            }
                        },
                    ]),
                Forms\Components\TextInput::make('tonnage')->label('Tonase netto')->numeric()
                    ->minValue(0.01)->maxValue(100)->step(0.01)->suffix('ton')->required()
                    ->live(onBlur: true)->extraInputAttributes(['class' => 'font-bold']),
                Forms\Components\TextInput::make('fuel_actual_liters')->label('Solar aktual (opsional)')->numeric()
                    ->minValue(0)->maxValue(1000)->step(0.01)->suffix('liter')
                    ->helperText('Isi bila ada data pengisian solar; dibandingkan dengan standar rute.'),
                Forms\Components\Textarea::make('notes')->label('Catatan')->rows(2),
            ]),

            Forms\Components\Section::make('Hitung otomatis')
                ->description('Diambil dari master tarif, rute dan harga solar.')
                ->icon('heroicon-o-calculator')
                ->columnSpan(['default' => 3, 'lg' => 1])
                ->schema([
                    Forms\Components\Placeholder::make('preview')->hiddenLabel()
                        ->content(fn (Get $get) => static::previewHtml($get)),
                ]),
        ]);
    }

    protected static function previewHtml(Get $get): HtmlString
    {
        $tariff = Tariff::activeFor($get('customer_id'), $get('haul_route_id'), $get('trip_date'));
        $route = $get('haul_route_id') ? HaulRoute::find($get('haul_route_id')) : null;
        $driver = $get('driver_id') ? Driver::find($get('driver_id')) : null;
        $c = Trip::calculate((float) $get('tonnage'), $tariff, $route, FuelPrice::priceOn($get('trip_date')), $driver);
        $margin = $c['amount'] - $c['hpp'];

        $row = fn ($label, $value, $bold = false) => '<div style="display:flex;justify-content:space-between;gap:12px;padding:6px 0;'
            .($bold ? 'font-weight:700;font-size:1.05rem;border-top:1px solid rgba(120,113,108,.3);margin-top:6px;padding-top:10px' : '')
            .'"><span>'.e($label).'</span><span style="text-align:right">'.e($value).'</span></div>';

        $html = $tariff ? '' : '<p style="color:#e11d48;margin-bottom:8px">Tarif belum ditemukan.</p>';
        $html .= $c['fuel_price'] > 0 ? '' : '<p style="color:#e11d48;margin-bottom:8px">Harga solar belum ada untuk tanggal ini.</p>';
        $html .= $row('Tarif', M::rupiah($c['rate']).' / '.$c['rate_basis']);
        $html .= $row('Nilai trip', M::rupiah($c['amount']));
        $html .= $row('Solar '.M::num($c['fuel_liters'], 0).' L × '.M::rupiah($c['fuel_price']), '− '.M::rupiah($c['fuel_cost']));
        $html .= $row('Uang jalan', '− '.M::rupiah($c['allowance']));
        if ($c['driver_bonus'] > 0) {
            $html .= $row('Premi sopir', '− '.M::rupiah($c['driver_bonus']));
        }
        $html .= $row('Margin trip', M::rupiah($margin), true);

        return new HtmlString($html);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('number', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['unit', 'driver', 'customer', 'haulRoute']))
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('No trip')->searchable()->sortable()
                    ->fontFamily('mono')->copyable(),
                Tables\Columns\TextColumn::make('trip_date')->label('Tanggal')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('unit.code')->label('Unit')->fontFamily('mono')->searchable(),
                Tables\Columns\TextColumn::make('driver.name')->label('Sopir')->searchable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Customer')->wrap()
                    ->description(fn (Trip $record) => $record->haulRoute?->code)->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ticket_number')->label('No tiket')->fontFamily('mono')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('tonnage')->label('Tonase')->numeric(2, ',', '.')->suffix(' t')
                    ->alignEnd()->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total')->numeric(2, ',', '.')->suffix(' t')),
                Tables\Columns\TextColumn::make('rate')->label('Tarif')->money('IDR', locale: 'id')->alignEnd()->toggleable(),
                Tables\Columns\TextColumn::make('amount')->label('Nilai')->money('IDR', locale: 'id')->alignEnd()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total')->money('IDR', locale: 'id')),
                Tables\Columns\TextColumn::make('hpp')->label('HPP')->money('IDR', locale: 'id')->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('fuel_actual_liters')->label('Solar aktual')->alignEnd()
                    ->formatStateUsing(fn ($state, Trip $record) => M::num((float) $state, 0).' L ('
                        .sprintf('%+d', round((float) $state - (float) $record->fuel_liters)).')')
                    ->color(fn ($state, Trip $record) => (float) $state > (float) $record->fuel_liters * 1.05 ? 'danger' : null)
                    ->tooltip('Selisih terhadap solar standar rute; merah bila lebih dari 5%.')
                    ->placeholder('–')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => strtoupper(Trip::STATUSES[$state] ?? $state))
                    ->color(fn ($state) => Trip::STATUS_COLORS[$state] ?? 'gray'),
            ])
            ->filters([
                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari tanggal')->native(false),
                        Forms\Components\DatePicker::make('until')->label('Sampai tanggal')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($x, $d) => $x->whereDate('trip_date', '>=', $d))
                        ->when($data['until'] ?? null, fn ($x, $d) => $x->whereDate('trip_date', '<=', $d)))
                    ->indicateUsing(function (array $data) {
                        $out = [];
                        if ($data['from'] ?? null) {
                            $out[] = 'Dari '.\Illuminate\Support\Carbon::parse($data['from'])->format('d M Y');
                        }
                        if ($data['until'] ?? null) {
                            $out[] = 'Sampai '.\Illuminate\Support\Carbon::parse($data['until'])->format('d M Y');
                        }

                        return $out;
                    }),
                Tables\Filters\SelectFilter::make('unit_id')->label('Unit')->relationship('unit', 'code')->multiple()->preload(),
                Tables\Filters\SelectFilter::make('driver_id')->label('Sopir')->relationship('driver', 'name')->preload(),
                Tables\Filters\SelectFilter::make('customer_id')->label('Customer')->relationship('customer', 'name')->preload(),
                Tables\Filters\SelectFilter::make('status')->options(Trip::STATUSES),
            ])
            ->filtersFormColumns(2)
            ->actions([
                Tables\Actions\Action::make('verify')->label('Verifikasi')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (Trip $record) => $record->status === 'draft' && static::canVerify() && static::canVerifyRecord($record))
                    ->requiresConfirmation()
                    ->modalDescription(fn (Trip $record) => "Pastikan tiket {$record->ticket_number} dan tonase {$record->tonnage} t sudah sesuai.")
                    ->action(fn (Trip $record) => static::verify(collect([$record]))),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('verifyBulk')->label('Verifikasi terpilih')->icon('heroicon-o-check-badge')
                    ->color('success')->requiresConfirmation()->deselectRecordsAfterCompletion()
                    ->visible(fn () => static::canVerify())
                    ->action(fn (Collection $records) => static::verify($records)),
            ]);
    }

    /** Pemisahan tugas: pembuat trip tidak memverifikasi trip-nya sendiri, kecuali Owner / Super Admin. */
    public static function canVerifyRecord(Trip $trip): bool
    {
        $user = auth()->user();

        return $trip->created_by !== $user?->id
            || $user?->role === 'owner'
            || Permissions::isSuperAdmin($user);
    }

    /** Verifikasi satu per satu; trip yang gagal dilewati dan dilaporkan, bukan menghentikan semuanya. */
    public static function verify($records): void
    {
        $ok = 0;
        $skipped = [];
        foreach ($records as $trip) {
            if ($trip->status !== 'draft') {
                continue;
            }
            if (! static::canVerifyRecord($trip)) {
                $skipped[] = "{$trip->number}: Anda yang menginput trip ini";
                continue;
            }
            try {
                $trip->update(['status' => 'verified', 'verified_by' => auth()->id(), 'verified_at' => now()]);
                $ok++;
            } catch (ValidationException $e) {
                $skipped[] = $trip->number.': '.collect($e->errors())->flatten()->first();
            }
        }

        $note = Notification::make()->title("{$ok} trip diverifikasi");
        if ($skipped) {
            $note->warning()->body(count($skipped).' dilewati — '.implode('; ', array_slice($skipped, 0, 5)).(count($skipped) > 5 ? '; …' : ''))->persistent();
        } else {
            $note->success();
        }
        $note->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrips::route('/'),
            'create' => Pages\CreateTrip::route('/create'),
            'edit' => Pages\EditTrip::route('/{record}/edit'),
        ];
    }
}
