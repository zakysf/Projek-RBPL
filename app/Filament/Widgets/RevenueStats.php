<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueStats extends BaseWidget
{
    protected function getStats(): array
    {
        return [

            Stat::make(
                'Total Pendapatan',
                'Rp ' . number_format(
                    Reservation::where('payment_status', 'paid')->sum('price'),
                    0,
                    ',',
                    '.'
                )
            )->color('success'),

            Stat::make(
                'Transaksi Paid',
                Reservation::where('payment_status', 'paid')->count()
            )->color('primary'),

            Stat::make(
                'Transaksi Unpaid',
                Reservation::where('payment_status', 'unpaid')->count()
            )->color('danger'),

        ];
    }
    public static function canView(): bool
    {
        return auth()->user()?->role === 'accounting';
    }
}