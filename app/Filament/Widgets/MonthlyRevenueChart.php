<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class MonthlyRevenueChart extends ChartWidget
{
    protected ?string $heading = 'Pendapatan Bulanan';

    protected function getData(): array
    {
        $data = Reservation::query()
            ->where('payment_status', 'paid')
            ->selectRaw('MONTH(reservation_time) as month, SUM(price) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $labels = [];
        $totals = [];

        foreach ($data as $item) {
            $labels[] = Carbon::create()->month($item->month)->format('F');
            $totals[] = $item->total;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pendapatan',
                    'data' => $totals,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar'; // bisa ganti 'line' kalau mau
    }

    public static function canView(): bool
    {
        return auth()->user()?->role === 'accounting';
    }
}