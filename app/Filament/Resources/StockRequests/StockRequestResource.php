<?php

namespace App\Filament\Resources\StockRequests;

use App\Filament\Resources\StockRequests\Pages\CreateStockRequest;
use App\Filament\Resources\StockRequests\Pages\EditStockRequest;
use App\Filament\Resources\StockRequests\Pages\ListStockRequests;
use App\Filament\Resources\StockRequests\Schemas\StockRequestForm;
use App\Filament\Resources\StockRequests\Tables\StockRequestsTable;
use App\Models\StockRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockRequestResource extends Resource
{
    protected static ?string $model = StockRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'item_name';

    public static function form(Schema $schema): Schema
    {
        return StockRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockRequestsTable::configure($table);
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
            'index' => ListStockRequests::route('/'),
            'create' => CreateStockRequest::route('/create'),
            'edit' => EditStockRequest::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return in_array(auth()->user()?->role, [
            'therapist',
            'purchasing',
        ]);
    }

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()?->role, [
            'therapist',
            'purchasing',
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()->role === 'therapist') {
            $query->where('therapist_id', auth()->user()->therapist_id);
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->role === 'therapist';
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
