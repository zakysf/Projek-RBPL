<?php

namespace App\Filament\Pages;

use BackedEnum;
use App\Models\Reservation;
use Filament\Pages\Page;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class TherapistSchedule extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public function getView(): string
    {
        return 'filament.pages.therapist-schedule';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Filament::auth()->user()?->role === 'therapist';
    }

    public function table(Table $table): Table
    {
        $user = Filament::auth()->user();

        return $table
            ->query(
                Reservation::query()
                    ->where('therapist_id', $user->therapist_id)
            )
            ->columns([
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer'),

                Tables\Columns\TextColumn::make('treatment.name')
                    ->label('Treatment'),

                Tables\Columns\TextColumn::make('reservation_time')
                    ->label('Tanggal')
                    ->date('d M Y'),

                Tables\Columns\TextColumn::make('reservation_time')
                    ->label('Jam')
                    ->time('H:i'),

                Tables\Columns\TextColumn::make('duration')
                    ->suffix(' menit'),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->colors([
                        'success' => 'paid',
                        'danger' => 'unpaid',
                    ]),
            ]);
    }
}