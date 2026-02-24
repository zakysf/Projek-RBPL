<?php

namespace App\Filament\Resources\Therapists;

use App\Filament\Resources\Therapists\Pages\CreateTherapist;
use App\Filament\Resources\Therapists\Pages\EditTherapist;
use App\Filament\Resources\Therapists\Pages\ListTherapists;
use App\Filament\Resources\Therapists\Schemas\TherapistForm;
use App\Filament\Resources\Therapists\Tables\TherapistsTable;
use App\Models\Therapist;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TherapistResource extends Resource
{
    protected static ?string $model = Therapist::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TherapistForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TherapistsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTherapists::route('/'),
            'create' => CreateTherapist::route('/create'),
            'edit' => EditTherapist::route('/{record}/edit'),
        ];
    }
}
