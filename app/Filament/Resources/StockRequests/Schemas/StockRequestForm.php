<?php

namespace App\Filament\Resources\StockRequests\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

class StockRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Select::make('therapist_id')
                    ->relationship('therapist', 'name')
                    ->default(auth()->user()->therapist_id)
                    ->disabled(fn () => auth()->user()->role === 'therapist')
                    ->dehydrated(fn () => true)
                    ->required(),

                TextInput::make('item_name')
                    ->label('Nama Barang')
                    ->required()
                    ->disabled(fn () => auth()->user()->role === 'purchasing'),

                TextInput::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->required()
                    ->disabled(fn () => auth()->user()->role === 'purchasing'),

                Textarea::make('notes')
                    ->label('Catatan')
                    ->disabled(fn () => auth()->user()->role === 'purchasing'),

                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->required()
                    ->disabled(fn () => auth()->user()->role === 'therapist'),
            ]);
    }
}
