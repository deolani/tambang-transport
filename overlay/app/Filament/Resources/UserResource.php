<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleAccess;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    use HasRoleAccess;

    public static function permissionPrefix(): string
    {
        return 'users';
    }

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'User';

    protected static ?int $navigationSort = 1;

    protected static function currentIsSuperAdmin(): bool
    {
        return Permissions::isSuperAdmin(auth()->user());
    }

    /** User biasa tidak boleh mengubah / menghapus akun Super Admin. */
    public static function canEdit(Model $record): bool
    {
        return static::allows('edit') && ($record->role !== Permissions::SUPER_ADMIN || static::currentIsSuperAdmin());
    }

    public static function canDelete(Model $record): bool
    {
        return static::allows('delete')
            && $record->getKey() !== auth()->id()
            && ($record->role !== Permissions::SUPER_ADMIN || static::currentIsSuperAdmin());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nama')->required(),
            Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            Forms\Components\Select::make('role')->label('Role')
                ->options(fn () => Role::options(includeSuperAdmin: static::currentIsSuperAdmin()))
                ->required()
                ->helperText('Hak akses tiap role diatur di Pengaturan › Role & Hak Akses (khusus Super Admin).'),
            Forms\Components\TextInput::make('password')->password()->revealable()
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn ($state) => filled($state))
                ->minLength(8)
                ->helperText('Minimal 8 karakter. Kosongkan bila tidak diganti.'),
            Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true)
                ->disabled(fn (?User $record) => $record?->getKey() === auth()->id())
                ->helperText('User nonaktif tidak bisa login.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('roleModel'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('role')->label('Role')->badge()
                    ->formatStateUsing(fn (User $record) => $record->role_name)
                    ->color(fn ($state) => $state === Permissions::SUPER_ADMIN ? 'danger' : 'primary'),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')->options(fn () => Role::options(true)),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->slideOver(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageUsers::route('/')];
    }
}
