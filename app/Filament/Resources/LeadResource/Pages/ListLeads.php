<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
use App\Models\Lead;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $leads = Lead::orderByDesc('created_at')->get();
                    $csv = "id,phone,name,email,intent,source,status,created_at\n";
                    foreach ($leads as $l) {
                        $csv .= implode(',', [
                            $l->id,
                            $l->phone,
                            '"' . str_replace('"', '""', $l->name ?? '') . '"',
                            $l->email ?? '',
                            $l->intent ?? '',
                            $l->source ?? '',
                            $l->status ?? '',
                            $l->created_at?->toIso8601String() ?? '',
                        ]) . "\n";
                    }
                    return response()->streamDownload(fn () => print($csv), 'leads-' . now()->format('Y-m-d') . '.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                }),
        ];
    }
}
