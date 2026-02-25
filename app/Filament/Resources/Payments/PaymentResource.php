<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\EditPayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Schemas\PaymentForm;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('reservation_id')
                    ->relationship('reservation', 'id')
                    ->disabled(),

                TextInput::make('amount')
                    ->numeric()
                    ->disabled(),

                Select::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'transfer' => 'Transfer',
                        'qris' => 'QRIS',
                    ])
                    ->required(),

                Select::make('payment_status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'paid' => 'Paid',
                        'refunded' => 'Refunded',
                    ])
                    ->required(),
            ]);
    }

    public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('reservation.id')
                    ->label('Reservation'),

                \Filament\Tables\Columns\TextColumn::make('amount')
                    ->money('IDR', true),

                \Filament\Tables\Columns\BadgeColumn::make('payment_status')
                    ->colors([
                        'danger' => 'unpaid',
                        'success' => 'paid',
                        'warning' => 'refunded',
                    ]),

                \Filament\Tables\Columns\TextColumn::make('payment_method'),

                \Filament\Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime(),
            ])
            ->headerActions([])
            ->actions([
                \Filament\Actions\Action::make('confirm')
                    ->label('Confirm')
                    ->color('success')
                    ->visible(fn ($record) => $record->payment_status === 'unpaid')
                    ->action(function ($record) {
                        $record->update([
                            'payment_status' => 'paid',
                            'paid_at' => now(),
                        ]);
                    }),
            ]);
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
            'index' => ListPayments::route('/'),
            'create' => CreatePayment::route('/create'),
            'edit' => EditPayment::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return in_array(auth()->user()->role ?? null, [
            'cashier',
            'accounting',
            'manager',
        ]);
    }

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()->role ?? null, [
            'cashier',
            'accounting',
            'manager',
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return $record->payment_status !== 'paid';
    }

    protected static function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['payment_status'] === 'paid') {
            $data['paid_at'] = now();
        }

        return $data;
    }
}
