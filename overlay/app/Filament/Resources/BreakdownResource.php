<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\BreakdownResource\Pages;
use App\Models\Breakdown;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BreakdownResource extends Resource
{
    use HasRoleAccess;

    public static function permissionPrefix(): string
    {
        return 'breakdowns';
    }

    protected static ?string $model = Breakdown::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Armada';

    protected static ?string $modelLabel = 'Breakdown';

    protected static ?string $pluralModelLabel = 'Breakdown Unit';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $n = Breakdown::whereNull('finished_at')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('unit_id')->label('Unit')->relationship('unit', 'code')
                ->searchable()->preload()->required(),
            Forms\Components\DateTimePicker::make('started_at')->label('Mulai rusak')->default(now())
                ->seconds(false)->required()->native(false),
            Forms\Components\DateTimePicker::make('finished_at')->label('Selesai diperbaiki')->seconds(false)
                ->native(false)->afterOrEqual('started_at')
                ->helperText('Kosongkan bila masih dalam perbaikan. Unit otomatis kembali Aktif saat diisi.'),
            Forms\Components\TextInput::make('description')->label('Kerusakan')->required()->maxLength(255),
            Forms\Components\TextInput::make('action_taken')->label('Tindakan / sparepart')->maxLength(255),
            Forms\Components\TextInput::make('cost')->label('Biaya perbaikan')->numeric()->prefix('Rp')->default(0)
                ->helperText('Masuk ke HPP unit di dashboard.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('No BD')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('unit.code')->label('Unit')->searchable(),
                Tables\Columns\TextColumn::make('started_at')->label('Mulai')->dateTime('d M Y H:i'),
                Tables\Columns\TextColumn::make('finished_at')->label('Selesai')->dateTime('d M Y H:i')->placeholder('—'),
                Tables\Columns\TextColumn::make('downtime_hours')->label('Downtime')->suffix(' jam')->alignEnd(),
                Tables\Columns\TextColumn::make('description')->label('Kerusakan')->wrap(),
                Tables\Columns\TextColumn::make('cost')->label('Biaya')->money('IDR', locale: 'id')->alignEnd()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('IDR', locale: 'id')),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => strtoupper(Breakdown::STATUSES[$state] ?? $state))
                    ->color(fn ($state) => $state === 'proses' ? 'danger' : 'success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(Breakdown::STATUSES),
                Tables\Filters\SelectFilter::make('unit_id')->label('Unit')->relationship('unit', 'code'),
            ])
            ->actions([
                Tables\Actions\Action::make('finish')->label('Selesai')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (Breakdown $record) => ! $record->finished_at && static::canEdit($record))
                    ->form([
                        Forms\Components\DateTimePicker::make('finished_at')->label('Selesai')->default(now())->seconds(false)->required(),
                        Forms\Components\TextInput::make('cost')->label('Biaya perbaikan')->numeric()->prefix('Rp')
                            ->default(fn (Breakdown $record) => $record->cost),
                    ])
                    ->action(fn (Breakdown $record, array $data) => $record->update($data)),
                Tables\Actions\EditAction::make()->slideOver(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageBreakdowns::route('/')];
    }
}
