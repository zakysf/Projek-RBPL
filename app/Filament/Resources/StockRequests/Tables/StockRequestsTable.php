<?php

namespace App\Filament\Resources\StockRequests\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class StockRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                \Filament\Tables\Columns\TextColumn::make('therapist.name')
                    ->label('Therapist')
                    ->searchable()
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('item_name')
                    ->label('Nama Barang')
                    ->searchable(),

                \Filament\Tables\Columns\TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->sortable(),

                \Filament\Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),

                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

            ])
            ->filters([

                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),

            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn ($record) =>
                        auth()->user()?->role === 'purchasing'
                        || (
                            auth()->user()?->role === 'therapist'
                            && $record->status === 'pending'
                        )
                    ),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }
}
