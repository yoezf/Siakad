<?php

namespace App\Filament\Sekolah\Resources\AssessmentComponents;

use App\Filament\Sekolah\Resources\AssessmentComponents\Pages\ManageAssessmentComponents;
use App\Filament\Sekolah\Support\Galat;
use App\Models\AssessmentComponent;
use App\Support\Tenancy\CurrentSchool;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;

class AssessmentComponentResource extends Resource
{
    protected static ?string $model = AssessmentComponent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'Penilaian';

    protected static ?string $modelLabel = 'komponen penilaian';

    protected static ?string $pluralModelLabel = 'Komponen penilaian';

    protected static ?string $slug = 'komponen-penilaian';

    // Hak akses diatur oleh PenilaianPolicy. Parameter closure Filament harus bernama $rule, $record, $action, dst.
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama komponen')->placeholder('Ulangan Harian')->required()->maxLength(60)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('school_id', CurrentSchool::id())),
            TextInput::make('weight')->label('Bobot (%)')->numeric()->integer()->minValue(1)->maxValue(100)->required()
                ->helperText('Nilai akhir dihitung dari rata-rata berbobot semua komponen aktif.'),
            TextInput::make('sort_order')->label('Urutan')->numeric()->integer()->minValue(1)->maxValue(250)->default(1)->required(),
            Toggle::make('active')->label('Aktif')->default(true)
                ->helperText('Komponen yang dihentikan tidak ikut perhitungan dan tidak bisa diisi, tetapi nilai lamanya tetap tersimpan.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(function () {
                $total = AssessmentComponent::where('active', true)->sum('weight');

                return $total === 100 || $total === 0 ? "Total bobot komponen aktif: {$total}." : "Total bobot komponen aktif: {$total}. Disarankan 100; nilai akhir tetap dihitung dengan membagi jumlah bobot.";
            })
            ->columns([
                TextColumn::make('sort_order')->label('Urutan')->sortable(),
                TextColumn::make('name')->label('Komponen')->searchable(),
                TextColumn::make('weight')->label('Bobot')->suffix('%'),
                IconColumn::make('active')->label('Aktif')->boolean(),
                TextColumn::make('scores_count')->label('Nilai tersimpan')->counts('scores'),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make()->using(function (Model $record, array $data, Action $action) {
                    try {
                        $record->update($data);

                        return $record;
                    } catch (ValidationException $e) {
                        Galat::beritahu($e);
                        $action->halt();
                    }
                }),
                DeleteAction::make()->before(function (AssessmentComponent $record, Action $action) {
                    if ($record->scores()->exists()) {
                        Notification::make()->danger()->persistent()->title('Komponen ini sudah dipakai nilai.')
                            ->body('Komponen yang sudah punya nilai tidak bisa dihapus. Matikan tombol "Aktif" untuk menghentikannya.')->send();
                        $action->halt();
                    }
                }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAssessmentComponents::route('/')];
    }
}
