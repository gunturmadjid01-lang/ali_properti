<?php

namespace App\Http\Controllers\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Inertia\Inertia;
use Inertia\Response;

trait RendersSeparatedManagementForm
{
    public function create(): Response
    {
        return $this->renderSeparatedForm();
    }

    public function edit(string $id): Response
    {
        $query = $this->modelClass()::query();
        if (method_exists($this, 'relations')) {
            $query->with($this->relations());
        }

        $row = $query->findOrFail($id);
        if (method_exists($this, 'abortIfLocked')) {
            $this->abortIfLocked($row);
        }

        return $this->renderSeparatedForm($row);
    }

    protected function renderSeparatedForm(?Model $row = null): Response
    {
        $fields = $this->fields();
        $initialData = collect($fields)->mapWithKeys(function (array $field) use ($row): array {
            $name = $field['name'];
            $value = $row?->getAttribute($name) ?? match ($field['type'] ?? 'text') {
                'checkboxes' => [],
                'checkbox' => (bool) ($field['defaultValue'] ?? false),
                default => $field['defaultValue'] ?? '',
            };

            if ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d');
            }
            if (is_bool($value) && ($field['type'] ?? '') === 'select') {
                $value = $value ? '1' : '0';
            }

            return [$name => $value];
        })->all();

        return Inertia::render('Admin/Management/Components/SeparatedManagementFormPage', [
            'title' => ($row ? 'Edit ' : 'Tambah ').$this->title(),
            'description' => $this->description(),
            'baseUrl' => route($this->routeName().'.index', absolute: false),
            'actionUrl' => $row
                ? route($this->routeName().'.update', $row->id, false)
                : route($this->routeName().'.store', absolute: false),
            'method' => $row ? 'put' : 'post',
            'fields' => $fields,
            'options' => $this->options(),
            'initialData' => $initialData,
        ]);
    }
}
