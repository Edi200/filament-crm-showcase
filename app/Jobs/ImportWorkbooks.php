<?php

namespace App\Jobs;

use App\Models\Machine;
use App\Models\Workbook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use Throwable;
use Carbon\Carbon;

class ImportWorkbooks implements ShouldQueue
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

        $csvPath = storage_path('app/csv/WbHeader.csv');

        if (! is_file($csvPath)) {
            Log::info('CSV import workbooks finished: source file missing.', [
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
                $serialNumber = trim((string) ($row['serial_number'] ?? ''));

                if ($id === '' || $serialNumber === '') {
                    $skipped++;
                    Log::info('Workbook CSV row skipped: missing id or serial number.', [
                        'line' => $line,
                        'id' => $id,
                        'serial_number' => $serialNumber,
                    ]);

                    continue;
                }

                if (! ctype_digit($id)) {
                    $errors++;
                    Log::info('Workbook CSV row error: id is not numeric.', [
                        'line' => $line,
                        'id' => $id,
                    ]);

                    continue;
                }

                $machine = Machine::query()
                    ->select(['id', 'serial_number'])
                    ->where('serial_number', $serialNumber)
                    ->first();

                if ($machine === null) {
                    $skipped++;
                    Log::info('Workbook CSV row skipped: machine serial not found.', [
                        'line' => $line,
                        'id' => $id,
                        'serial_number' => $serialNumber,
                    ]);

                    continue;
                }

                $normalized = [
                    'machine_id' => $machine->id,
                    'work_date' => $this->normalizeDate($row['work_date'] ?? null),
                    'work_hour' => $this->normalizeDecimal($row['work_hour'] ?? null, 2),
                    'problem' => trim((string) ($row['problem'] ?? '')),
                    'remark' => $this->normalizeNullableText($row['remark'] ?? null),
                ];

                if ($normalized['work_date'] === null || $normalized['problem'] === '') {
                    $skipped++;
                    Log::info('Workbook CSV row skipped: invalid required workbook fields.', [
                        'line' => $line,
                        'id' => $id,
                        'work_date' => $row['work_date'] ?? null,
                        'problem' => $row['problem'] ?? null,
                    ]);

                    continue;
                }

                $recordId = (int) $id;
                $workbook = Workbook::query()->find($recordId);

                if ($workbook === null) {
                    Workbook::unguarded(function () use ($recordId, $normalized): void {
                        Workbook::query()->updateOrCreate(
                            ['id' => $recordId],
                            ['id' => $recordId, ...$normalized]
                        );
                    });

                    $inserted++;

                    continue;
                }

                $existing = [
                    'machine_id' => (int) $workbook->machine_id,
                    'work_date' => optional($workbook->work_date)->format('Y-m-d'),
                    'work_hour' => $this->normalizeDecimal($workbook->work_hour, 2),
                    'problem' => (string) $workbook->problem,
                    'remark' => $workbook->remark === null ? null : (string) $workbook->remark,
                ];

                if ($existing === $normalized) {
                    continue;
                }

                Workbook::unguarded(function () use ($recordId, $normalized): void {
                    Workbook::query()->updateOrCreate(
                        ['id' => $recordId],
                        ['id' => $recordId, ...$normalized]
                    );
                });
                $updated++;
            } catch (Throwable $exception) {
                $errors++;

                Log::info('Workbook CSV row processing error.', [
                    'line' => ($index + 2),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        Log::info('CSV import workbooks finished.', [
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

    private function normalizeDate(mixed $value): ?string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        $normalized = trim($normalized, '"');

        return $normalized === '' ? null : $normalized;
    }
}
