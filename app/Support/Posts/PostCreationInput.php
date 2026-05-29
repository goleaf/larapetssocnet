<?php

namespace App\Support\Posts;

use App\Http\Requests\Posts\PostCreationRequest;
use App\Models\Identity\User;

final class PostCreationInput
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(private readonly array $data) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidatedArray(array $data): self
    {
        return new self($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromUserInput(User $user, array $data): self
    {
        return self::fromValidatedArray(PostCreationRequest::validateForUser($user, $data));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
