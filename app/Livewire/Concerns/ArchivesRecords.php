<?php

namespace App\Livewire\Concerns;

use App\Exceptions\ArchiveBlockedException;
use App\Services\ArchiveService;
use Illuminate\Database\Eloquent\Model;

trait ArchivesRecords
{
    public bool $showArchived = false;

    public function updatedShowArchived(): void
    {
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    public function archiveRecord(int $id, string $modelClass): void
    {
        /** @var Model $record */
        $record = $modelClass::query()->findOrFail($id);

        $this->authorize('delete', $record);

        try {
            app(ArchiveService::class)->archive($record);
        } catch (ArchiveBlockedException $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        }

        session()->flash('success', 'Registro arquivado. Pode ser restaurado em até '.config('archive.retention_days', 30).' dias.');

        $this->afterArchiveRecord($record);
    }

    public function restoreRecord(int $id, string $modelClass): void
    {
        /** @var Model $record */
        $record = $modelClass::onlyTrashed()->findOrFail($id);

        $this->authorize('restore', $record);

        app(ArchiveService::class)->restore($record);

        session()->flash('success', 'Registro restaurado com sucesso.');

        $this->afterRestoreRecord($record);
    }

    protected function afterArchiveRecord(Model $record): void
    {
        if (property_exists($this, 'editingId') && $this->editingId === $record->getKey()) {
            if (property_exists($this, 'showForm')) {
                $this->showForm = false;
            }
            $this->editingId = null;
        }
    }

    protected function afterRestoreRecord(Model $record): void
    {
        // Hook para componentes que precisam reagir após restaurar.
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $modelClass
     * @return \Illuminate\Database\Eloquent\Builder<T>
     */
    protected function archivableQuery(string $modelClass)
    {
        return $this->showArchived
            ? $modelClass::onlyTrashed()
            : $modelClass::query();
    }
}
