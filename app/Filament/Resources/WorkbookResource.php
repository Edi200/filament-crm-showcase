<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkbookResource\Pages\ListWorkbooks;
use App\Filament\Resources\WorkbookResource\Pages\ManageWorkbookImages;
use App\Filament\Resources\WorkbookResource\Pages\ViewWorkbookInfo;
use App\Filament\Resources\WorkbookResource\RelationManagers\ImageRelationManager;
use App\Filament\Resources\WorkbookResource\RelationManagers\WorkbookDetailRelationManager;
use App\Models\Customer;
use App\Models\WbDetail;
use App\Models\Workbook;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class WorkbookResource extends Resource
{
    protected static ?string $model = Workbook::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Service';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Select::make('machine_id')
                ->label('Machine')
                ->relationship('machine', 'model')
                ->searchable()
                ->preload()
                ->required(),
            DatePicker::make('work_date')
                ->required(),
            TextInput::make('work_hour')
                ->numeric()
                ->step('0.01')
                ->required(),
            Textarea::make('problem')
                ->label('Workbook Number')
                ->required(),
            Textarea::make('remark'),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Workbook')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('problem')
                            ->label('Workbook Number'),
                        TextEntry::make('work_date')
                            ->date(),
                        TextEntry::make('work_hour'),
                        TextEntry::make('remark'),
                    ]),
                Section::make('Machine')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('machine.model')
                            ->label('Machine Model'),
                        TextEntry::make('machine.serial_number')
                            ->label('Serial Number'),
                        TextEntry::make('machine.type')
                            ->label('Machine Type'),
                        TextEntry::make('machine.customer.name')
                            ->label('Customer Name'),
                    ]),
                Section::make('Workbook Details')
                    ->schema([
                        RepeatableEntry::make('wbDetails')
                            ->label('')
                            ->schema([
                                TextEntry::make('sparePart.name')
                                    ->label('Spare Part'),
                                TextEntry::make('quantity'),
                                TextEntry::make('price'),
                                TextEntry::make('subtotal')
                                    ->state(fn (WbDetail $record): float => (float) $record->quantity * (float) $record->price),
                            ])
                            ->columns(4),
                    ]),
                Section::make('Images')
                    ->schema([
                        RepeatableEntry::make('images')
                            ->label('')
                            ->schema([
                                ImageEntry::make('image_path')
                                    ->disk('local')
                                    ->label('Image'),
                                TextEntry::make('created_at')
                                    ->dateTime('Y-m-d H:i'),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('problem')
                    ->label('Workbook Number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('machine.model')
                    ->label('Machine Model')
                    ->searchable(),
                TextColumn::make('machine.customer.name')
                    ->label('Customer Name')
                    ->searchable()
                    ->sortable(
                        query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                            Customer::query()
                                ->select('name')
                                ->whereColumn('customers.id', 'machines.customer_id')
                                ->limit(1),
                            $direction,
                        ),
                    ),
                TextColumn::make('work_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('work_hour'),
            ])
            ->defaultSort('work_date', 'desc')
            ->recordUrl(fn (Workbook $record): string => WorkbookResource::getUrl('viewInfo', ['record' => $record]))
            ->stackedOnMobile()
            ->recordActions([
                Action::make('viewInfo')
                    ->label('View Info')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (Workbook $record): string => static::getUrl('viewInfo', ['record' => $record])),
                Action::make('manageImages')
                    ->label('Manage Images')
                    ->icon('heroicon-o-photo')
                    ->color('warning')
                    ->url(fn (Workbook $record): string => static::getUrl('manageImages', ['record' => $record])),
            ])
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 20, 30, 40, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkbooks::route('/'),
            'viewInfo' => ViewWorkbookInfo::route('/{record}/info'),
            'manageImages' => ManageWorkbookImages::route('/{record}/images'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            WorkbookDetailRelationManager::class,
            ImageRelationManager::class,
        ];
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasAnyRole(['admin', 'mechanic']) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return Auth::user()?->hasAnyRole(['admin', 'mechanic']) ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole('admin') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->hasRole('admin') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->hasRole('admin') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return Auth::user()?->hasRole('admin') ?? false;
    }
}
