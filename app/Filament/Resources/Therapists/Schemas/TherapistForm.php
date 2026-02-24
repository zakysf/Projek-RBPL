<?php

namespace App\Filament\Resources\Therapists\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TherapistForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('phone')
                    ->tel()
                    ->maxLength(20),

                TextInput::make('specialization')
                    ->maxLength(255),

                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}