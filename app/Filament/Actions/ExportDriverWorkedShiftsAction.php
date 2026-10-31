<?php

namespace App\Filament\Actions;

use App\Models\Driver;
use App\Services\DriverWorkedShiftsReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Tables\Actions\Action as TableAction;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportDriverWorkedShiftsAction
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function formSchema(): array
    {
        $today = now('Europe/Riga')->toDateString();
        $monthStart = now('Europe/Riga')->startOfMonth()->toDateString();

        return [
            Forms\Components\DatePicker::make('date_from')
                ->label('From')
                ->required()
                ->default($monthStart),
            Forms\Components\DatePicker::make('date_to')
                ->label('To')
                ->required()
                ->default($today),
            Forms\Components\Toggle::make('include_booked')
                ->label('Include booked (not only completed)')
                ->default(false)
                ->helperText('Off = completed shifts only (worked). On = also upcoming/booked.'),
            Forms\Components\Select::make('format')
                ->label('Format')
                ->options([
                    'csv' => 'CSV',
                    'pdf' => 'PDF',
                ])
                ->default('csv')
                ->required(),
        ];
    }

    public static function makeTableAction(): TableAction
    {
        return TableAction::make('exportWorkedShifts')
            ->label('Export worked shifts')
            ->icon('heroicon-o-arrow-down-tray')
            ->form(self::formSchema())
            ->action(function (array $data, Driver $record) {
                return self::download($record, $data);
            });
    }

    /**
     * @param  array{date_from: string, date_to: string, include_booked?: bool, format: string}  $data
     */
    public static function download(Driver $driver, array $data): StreamedResponse|\Illuminate\Http\Response
    {
        $report = app(DriverWorkedShiftsReportService::class)->build(
            $driver,
            $data['date_from'],
            $data['date_to'],
            (bool) ($data['include_booked'] ?? false)
        );

        $slug = str($driver->name)->slug('_')->limit(40, '')->toString() ?: ('driver_'.$driver->id);
        $base = 'worked-shifts_'.$slug.'_'.$report['date_from'].'_to_'.$report['date_to'];

        if (($data['format'] ?? 'csv') === 'pdf') {
            $pdf = Pdf::loadView('filament.reports.driver-worked-shifts', ['report' => $report])
                ->setPaper('a4', 'landscape');

            return response()->streamDownload(
                fn () => print($pdf->output()),
                $base.'.pdf',
                ['Content-Type' => 'application/pdf']
            );
        }

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'shift_id',
                'date',
                'start',
                'end',
                'hours',
                'station',
                'vehicle',
                'status',
                'started_via_bot_at',
                'driver_id',
                'driver_name',
            ]);
            foreach ($report['shifts'] as $row) {
                fputcsv($out, [
                    $row['shift_id'],
                    $row['date'],
                    $row['start'],
                    $row['end'],
                    $row['hours'],
                    $row['station'],
                    $row['vehicle'],
                    $row['status'],
                    $row['started_via_bot_at'] ?? '',
                    $report['driver_id'],
                    $report['driver_name'],
                ]);
            }
            fputcsv($out, []);
            fputcsv($out, ['total_shifts', $report['shift_count']]);
            fputcsv($out, ['total_hours', $report['total_hours']]);
            fputcsv($out, ['period', $report['date_from'].' to '.$report['date_to']]);
            fputcsv($out, ['timezone', $report['timezone']]);
            fclose($out);
        }, $base.'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
