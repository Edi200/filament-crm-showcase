<?php

namespace App\Jobs;

use App\Models\SparePart;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use Throwable;

class ImportSpareParts implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        $csvPath = storage_path('app/csv/parts.csv');

        if (! is_file($csvPath)) {
            Log::info('CSV import spare parts finished: source file missing.', [
                'path' => $csvPath,
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => 1,
            ]);

            return;
        }

        $reader = Reader::createFromPath($csvPath, 'r');
        $reader->setDelimiter(';');
        $reader->setHeaderOffset(0);

        foreach ($reader->getRecords() as $index => $row) {
            try {
                $line = $index + 2;

                if ($this->isEmptyRow($row)) {
                    $skipped++;

                    continue;
                }

                $id = trim((string) ($row['id'] ?? ''));
                $name = trim((string) ($row['name'] ?? ''));

                if ($id === '' || $name === '') {
                    $skipped++;
                    Log::info('Spare parts CSV row skipped: missing id or name.', [
                        'line' => $line,
                        'id' => $id,
                        'name' => $name,
                    ]);

                    continue;
                }

                if (! ctype_digit($id)) {
                    $errors++;
                    Log::info('Spare parts CSV row error: id is not numeric.', [
                        'line' => $line,
                        'id' => $id,
                    ]);

                    continue;
                }

                $normalized = [
                    'name' => $name,
                    'category' => trim((string) ($row['category'] ?? '')),
                    'price' => $this->normalizeDecimal($row['price'] ?? null, 2),
                    'unit' => trim((string) ($row['unit'] ?? '')),
                    'quantity' => $this->normalizeDecimal($row['quantity'] ?? null, 3),
                ];

                $recordId = (int) $id;
                $sparePart = SparePart::query()->find($recordId);

                if ($sparePart === null) {
                    SparePart::unguarded(function () use ($recordId, $normalized): void {
                        SparePart::query()->updateOrCreate(
                            ['id' => $recordId],
                            ['id' => $recordId, ...$normalized]
                        );
                    });

                    $inserted++;

                    continue;
                }

                $existing = [
                    'name' => (string) $sparePart->name,
                    'category' => (string) $sparePart->category,
                    'price' => $this->normalizeDecimal($sparePart->price, 2),
                    'unit' => (string) $sparePart->unit,
                    'quantity' => $this->normalizeDecimal($sparePart->quantity, 3),
                ];

                if ($existing === $normalized) {
                    continue;
                }

                SparePart::unguarded(function () use ($recordId, $normalized): void {
                    SparePart::query()->updateOrCreate(
                        ['id' => $recordId],
                        ['id' => $recordId, ...$normalized]
                    );
                });
                $updated++;
            } catch (Throwable $exception) {
                $errors++;

                Log::info('Spare parts CSV row processing error.', [
                    'line' => ($index + 2),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        Log::info('CSV import spare parts finished.', [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeDecimal(mixed $value, int $scale): string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return number_format(0, $scale, '.', '');
        }

        return number_format((float) str_replace(',', '.', $raw), $scale, '.', '');
    }
}
