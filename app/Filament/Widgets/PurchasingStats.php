<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\StockRequest;

class PurchasingStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make(
                'Pending',
                StockRequest::where('status', 'pending')->count()
            )->color('warning'),

            Stat::make(
                'Approved',
                StockRequest::where('status', 'approved')->count()
            )->color('success'),

            Stat::make(
                'Rejected',
                StockRequest::where('status', 'rejected')->count()
            )->color('danger'),
        ];
    }
    public static function canView(): bool
    {
        return auth()->user()?->role === 'purchasing';
    }
}
