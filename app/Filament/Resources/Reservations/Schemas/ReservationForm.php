<?php

namespace App\Filament\Resources\Reservations\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;

class ReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('customer_name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('customer_phone')
                    ->required()
                    ->tel(),

                Select::make('treatment_id')
                    ->relationship(
                        name: 'treatment',
                        titleAttribute: 'name'
                    )
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $treatment = \App\Models\Treatment::find($state);

                        if ($treatment) {
                            $set('duration', $treatment->duration);
                        }
                    }),

                Hidden::make('duration'),

                Select::make('therapist_id')
                    ->label('Therapist')
                    ->relationship('therapist', 'name')
                    ->required(),

                DateTimePicker::make('reservation_time')
                    ->required()
                    ->rule(function ($get, $record) {
                        return function ($attribute, $value, $fail) use ($get, $record) {

                            $therapistId = $get('therapist_id');
                            $duration = $get('duration');

                            if (!$therapistId || !$duration) {
                                return;
                            }

                            $newStart = \Carbon\Carbon::parse($value);
                            $newEnd = $newStart->copy()->addMinutes($duration);

                            $reservations = \App\Models\Reservation::where('therapist_id', $therapistId)
                                ->where('status', '!=', 'cancelled')
                                ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                ->get();

                            foreach ($reservations as $reservation) {

                                $existingStart = \Carbon\Carbon::parse($reservation->reservation_time);
                                $existingEnd = $existingStart->copy()->addMinutes($reservation->duration);

                                if ($existingStart < $newEnd && $existingEnd > $newStart) {
                                    $fail('Therapist memiliki jadwal yang bentrok dengan waktu tersebut.');
                                    return;
                                }
                            }
                        };
                    }),

                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('pending')
                    ->required(),
            ]);
    }
}