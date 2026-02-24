<?php

namespace App\Filament\Resources\Treatments\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;

class TreatmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),

                TextInput::make('duration')
                    ->numeric()
                    ->required()
                    ->suffix('minutes'),

                TextInput::make('price')
                    ->numeric()
                    ->required()
                    ->prefix('Rp')
                    ->minValue(0),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),

                Select::make('therapists')
                    ->relationship('therapists', 'name')
                    ->multiple()
                    ->preload()
            ]);
    }
}