<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CashierStats extends BaseWidget
{
    protected function getStats(): array
    {
        return [

            Stat::make(
                'Transaksi Hari Ini',
                Reservation::whereDate('reservation_time', now())->count()
            )->color('primary'),

            Stat::make(
                'Unpaid Hari Ini',
                Reservation::whereDate('reservation_time', now())
                    ->where('payment_status', 'unpaid')
                    ->count()
            )->color('danger'),

            Stat::make(
                'Pendapatan Hari Ini',
                'Rp ' . number_format(
                    Reservation::whereDate('reservation_time', now())
                        ->where('payment_status', 'paid')
                        ->sum('price'),
                    0,
                    ',',
                    '.'
                )
            )->color('success'),

        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->role === 'cashier';
    }
}