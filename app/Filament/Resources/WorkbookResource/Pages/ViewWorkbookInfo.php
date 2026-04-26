<?php

namespace App\Filament\Resources\WorkbookResource\Pages;

use App\Filament\Resources\WorkbookResource;
use App\Models\Image;
use App\Models\WbDetail;
use App\Models\Workbook;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ViewWorkbookInfo extends ViewRecord
{
    protected static string $resource = WorkbookResource::class;

    protected string $view = 'filament.resources.workbook-resource.pages.view-workbook-info';

    public function getBreadcrumb(): string
    {
        return 'View Info';
    }

    public function getTitle(): string
    {
        return 'Workbook Info';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->record)
            ->columns(1)
            ->components([
                Section::make('Machine & Workbook details')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('problem')
                            ->label('Workbook Number'),
                        TextEntry::make('work_date')
                            ->label('Work Date')
                            ->date(),
                        TextEntry::make('work_hour')
                            ->label('Work Hour'),
                        TextEntry::make('remark')
                            ->label('Remark')
                            ->placeholder('-'),
                        TextEntry::make('machine.serial_number')
                            ->label('Serial Number')
                            ->placeholder('-'),
                        TextEntry::make('machine.model')
                            ->label('Model')
                            ->placeholder('-'),
                        TextEntry::make('machine.customer.name')
                            ->label('Owner Name')
                            ->placeholder('-'),
                        TextEntry::make('machine.customer.phone')
                            ->label('Owner Phone')
                            ->placeholder('-'),
                    ]),
            ]);
    }

    protected function resolveRecord(int|string $key): Model
    {
        /** @var Workbook $record */
        $record = parent::resolveRecord($key);

        $record->loadMissing([
            'machine.customer',
            'wbDetails.sparePart',
            'images',
        ]);

        return $record;
    }

    /**
     * @return Collection<int, array{name: string, quantity: string, price: string, subtotal: string}>
     */
    public function getSparePartRowsProperty(): Collection
    {
        return $this->record->wbDetails
            ->map(function (WbDetail $detail): array {
                $quantity = (float) $detail->quantity;
                $price = (float) $detail->price;
                $subtotal = $quantity * $price;

                return [
                    'name' => (string) ($detail->sparePart?->name ?? '-'),
                    'quantity' => number_format($quantity, 2, '.', ','),
                    'price' => number_format($price, 2, '.', ','),
                    'subtotal' => number_format($subtotal, 2, '.', ','),
                ];
            });
    }

    public function getGrossTotalProperty(): string
    {
        $grossTotal = $this->record->wbDetails
            ->sum(fn (WbDetail $detail): float => (float) $detail->quantity * (float) $detail->price);

        return number_format($grossTotal, 2, '.', ',');
    }

    /**
     * @return Collection<int, string>
     */
    public function getImageUrlsProperty(): Collection
    {
        return $this->record->images
            ->map(fn (Image $image): ?string => $this->resolveImageUrl($image->image_path))
            ->filter()
            ->values();
    }

    private function resolveImageUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk('local');

        try {
            return $storage->temporaryUrl($path, now()->addMinutes(30));
        } catch (Throwable) {
            return $storage->url($path);
        }
    }
}
