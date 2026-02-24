<?php

namespace App\Filament\Pages;

use BackedEnum;
use App\Models\Reservation;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class CashierDashboard extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Cashier Dashboard';

    public static function shouldRegisterNavigation(): bool
    {
        return Filament::auth()->user()?->role === 'cashier';
    }

    public function getView(): string
    {
        return 'filament.pages.cashier-dashboard';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\CashierStats::class,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Reservation::query()
                    ->whereDate('reservation_time', now()->toDateString())
            )
            ->columns([
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable(),

                Tables\Columns\TextColumn::make('treatment.name')
                    ->label('Treatment'),

                Tables\Columns\TextColumn::make('reservation_time')
                    ->label('Jam')
                    ->time(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR'),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->colors([
                        'success' => 'paid',
                        'danger' => 'unpaid',
                    ]),
            ])
            ->defaultSort('reservation_time', 'asc');
    }
}