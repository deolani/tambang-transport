<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\Role;
use App\Support\Permissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Pengaturan › Role & Hak Akses — khusus Super Admin.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?string $modelLabel = 'Role';

    protected static ?string $pluralModelLabel = 'Role & Hak Akses';

    protected static ?string $slug = 'hak-akses';

    protected static ?int $navigationSort = 2;

    protected static function isSuperAdmin(): bool
    {
        return Permissions::isSuperAdmin(auth()->user());
    }

    public static function canViewAny(): bool
    {
        return static::isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return static::isSuperAdmin();
    }

    /** Role Super Admin selalu punya semua akses, jadi tidak diedit. */
    public static function canEdit(Model $record): bool
    {
        return static::isSuperAdmin() && $record->key !== Permissions::SUPER_ADMIN;
    }

    public static function canDelete(Model $record): bool
    {
        return static::isSuperAdmin() && ! $record->is_system && $record->users()->doesntExist();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /** Nama field checkbox untuk satu modul. */
    public static function field(string $module): string
    {
        return 'perm_'.$module;
    }

    /** permissions[] -> ['perm_trips' => ['view','verify'], ...] */
    public static function splitPermissions(array $permissions): array
    {
        $out = [];
        foreach (Permissions::groups() as $module => [, $actions]) {
            $out[static::field($module)] = array_values(array_filter(
                array_keys($actions),
                fn ($a) => in_array("{$module}.{$a}", $permissions, true),
            ));
        }

        return $out;
    }

    /** Kebalikan splitPermissions(), sekaligus membuang field perm_* dari data. */
    public static function mergePermissions(array $data): array
    {
        $perms = [];
        foreach (Permissions::groups() as $module => [, $actions]) {
            foreach ((array) ($data[static::field($module)] ?? []) as $a) {
                if (array_key_exists($a, $actions)) {
                    $perms[] = "{$module}.{$a}";
                }
            }
            unset($data[static::field($module)]);
        }
        unset($data['copy_from']);
        $data['permissions'] = array_values(array_unique($perms));

        return $data;
    }

    public static function form(Form $form): Form
    {
        $sections = [];
        foreach (Permissions::groups() as $module => [$label, $actions]) {
            $sections[] = Forms\Components\Section::make($label)
                ->compact()
                ->collapsible()
                ->schema([
                    Forms\Components\CheckboxList::make(static::field($module))
                        ->hiddenLabel()
                        ->options($actions)
                        ->columns(['default' => 2, 'md' => 4])
                        ->gridDirection('row')
                        ->bulkToggleable(),
                ]);
        }

        return $form->columns(1)->schema([
            Forms\Components\Section::make('Role')->columns(['default' => 1, 'md' => 3])->schema([
                Forms\Components\TextInput::make('name')->label('Nama role')->required()->maxLength(80)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set, Get $get, string $operation) {
                        if ($operation === 'create' && blank($get('key'))) {
                            $set('key', str($state)->snake()->limit(30, '')->toString());
                        }
                    }),
                Forms\Components\TextInput::make('key')->label('Kode role')
                    ->helperText('Huruf kecil, angka, garis bawah. Tidak bisa diubah.')
                    ->required()->maxLength(30)->regex('/^[a-z][a-z0-9_]*$/')
                    ->unique(ignoreRecord: true)
                    ->notIn([Permissions::SUPER_ADMIN])
                    ->disabled(fn (string $operation) => $operation === 'edit')
                    ->dehydrated(fn (string $operation) => $operation === 'create'),
                Forms\Components\Select::make('copy_from')->label('Salin hak akses dari')
                    ->placeholder('— tidak —')
                    ->options(fn () => Role::options())
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set) {
                        if (! $state) {
                            return;
                        }
                        $perms = (array) Role::where('key', $state)->value('permissions');
                        foreach (static::splitPermissions($perms) as $field => $value) {
                            $set($field, $value);
                        }
                    }),
                Forms\Components\TextInput::make('description')->label('Keterangan')->maxLength(255)->columnSpanFull(),
            ]),
            Forms\Components\Placeholder::make('hint')->hiddenLabel()->content(new HtmlString(
                '<p style="opacity:.8">Centang aksi yang boleh dilakukan role ini. Menu tanpa centang <b>Lihat</b> '
                .'tidak akan muncul. Perubahan berlaku saat user membuka halaman berikutnya.</p>'
            )),
            ...$sections,
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->modifyQueryUsing(fn ($query) => $query->withCount('users'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Role')->weight('bold')
                    ->description(fn (Role $record) => $record->description)->wrap(),
                Tables\Columns\TextColumn::make('key')->label('Kode')->badge()
                    ->color(fn (Role $record) => $record->isSuperAdmin() ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('users_count')->label('Jumlah user')->alignEnd(),
                Tables\Columns\TextColumn::make('permissions')->label('Hak akses')->alignEnd()
                    ->state(fn (Role $record) => $record->isSuperAdmin()
                        ? 'Semua'
                        : count((array) $record->permissions).' dari '.count(Permissions::all())),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Atur hak akses'),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
