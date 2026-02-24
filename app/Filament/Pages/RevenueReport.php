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

class RevenueReport extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function shouldRegisterNavigation(): bool
    {
        return Filament::auth()->user()?->role === 'accounting';
    }

    public function getView(): string
    {
        return 'filament.pages.revenue-report';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\RevenueStats::class,
            \App\Filament\Widgets\MonthlyRevenueChart::class,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Reservation::query()
                    ->where('payment_status', 'paid')
            )
            ->columns([
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable(),

                Tables\Columns\TextColumn::make('treatment.name')
                    ->label('Treatment'),

                Tables\Columns\TextColumn::make('reservation_time')
                    ->label('Tanggal')
                    ->date('d M Y'),

                Tables\Columns\TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR'),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->colors([
                        'success' => 'paid',
                    ]),
            ])
            ->defaultSort('reservation_time', 'desc');
    }
}