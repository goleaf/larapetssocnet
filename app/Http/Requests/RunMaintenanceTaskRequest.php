<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunMaintenanceTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAppRole('admin') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'chunk' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'queue' => ['nullable', 'string', 'max:120'],
            'path' => ['nullable', 'string', 'max:255'],
            'recount' => ['nullable', 'boolean'],
            'import_legacy' => ['nullable', 'boolean'],
            'remove_dark' => ['nullable', 'boolean'],
            'dry_run' => ['nullable', 'boolean'],
            'confirm' => ['nullable', Rule::in(['1', 1, true, 'on'])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function taskOptions(): array
    {
        return [
            'chunk' => $this->integer('chunk') ?: null,
            'days' => $this->integer('days') ?: null,
            'seconds' => $this->integer('seconds') ?: null,
            'queue' => $this->input('queue'),
            'path' => $this->input('path'),
            'recount' => $this->boolean('recount'),
            'import_legacy' => $this->boolean('import_legacy'),
            'remove_dark' => $this->boolean('remove_dark'),
            'dry_run' => $this->boolean('dry_run'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'chunk.min' => 'Chunk size must be at least 1.',
            'seconds.min' => 'Pause duration must be at least 1 second.',
            'queue.max' => 'Queue name is too long.',
            'path.max' => 'Path is too long.',
        ];
    }
}
