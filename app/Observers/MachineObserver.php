<?php

namespace App\Observers;

use App\Enums\MachineType;
use App\Models\Machine;

class MachineObserver
{
    /**
     * Handle the Machine "creating" event.
     */
    public function creating(Machine $machine): void
    {
        $machine->machine_number = $this->nextMachineNumber(
            (int) $machine->year,
            $this->normalizeType($machine->type),
        );
    }

    /**
     * Handle the Machine "updating" event.
     */
    public function updating(Machine $machine): void
    {
        if (! $machine->isDirty(['year', 'type'])) {
            return;
        }

        $machine->machine_number = $this->nextMachineNumber(
            (int) $machine->year,
            $this->normalizeType($machine->type),
            $machine->id,
        );
    }

    private function nextMachineNumber(int $year, string $type, ?int $ignoreId = null): int
    {
        return (int) Machine::query()
            ->where('year', $year)
            ->where('type', $type)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->max('machine_number') + 1;
    }

    private function normalizeType(string|MachineType $type): string
    {
        return $type instanceof MachineType ? $type->value : $type;
    }
}
