<?php

namespace App\Http\Requests\Messaging;

use App\Models\Identity\User;
use App\Models\Messaging\Message;
use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $receiver = $this->route('peer') ?? $this->route('receiver') ?? $this->route('user');

        if (! $receiver instanceof User || ! $this->user()) {
            return false;
        }

        return $this->user()->can('create', [Message::class, $receiver]);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'encoding:utf-8', 'max:5000'],
            'marketplace_listing_id' => ['nullable', 'integer', 'exists:marketplace_listings,id'],
        ];
    }
}
