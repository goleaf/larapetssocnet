<?php

namespace App\Http\Requests\Profile;

use App\Services\ProfilePortfolioService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProfilePortfolioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('update', $user);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'portfolio_posts' => ['nullable', 'array', 'max:'.ProfilePortfolioService::MAX_POSTS],
            'portfolio_posts.*' => ['integer', 'distinct'],
            'portfolio_positions' => ['nullable', 'array'],
            'portfolio_positions.*' => ['nullable', 'integer', 'min:1', 'max:'.ProfilePortfolioService::MAX_POSTS],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if ($user === null || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $postIds = $this->portfolioPostIds();

                if ($postIds === []) {
                    return;
                }

                $eligibleIds = app(ProfilePortfolioService::class)->eligiblePostIds($user, $postIds);

                if (count($eligibleIds) !== count($postIds)) {
                    $validator->errors()->add(
                        'portfolio_posts',
                        'Portfolio posts must be published public posts from your own profile.'
                    );
                }
            },
        ];
    }

    /**
     * @return list<int>
     */
    public function portfolioPostIds(): array
    {
        return collect((array) $this->input('portfolio_posts', []))
            ->filter(fn (mixed $postId): bool => is_numeric($postId))
            ->map(fn (mixed $postId): int => (int) $postId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function portfolioPositions(): array
    {
        return collect((array) $this->input('portfolio_positions', []))
            ->filter(fn (mixed $position, mixed $postId): bool => is_numeric($postId) && is_numeric($position))
            ->mapWithKeys(fn (mixed $position, mixed $postId): array => [(int) $postId => (int) $position])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'portfolio_posts.max' => 'Choose up to 12 posts for your portfolio.',
            'portfolio_posts.*.distinct' => 'Each portfolio post can only be selected once.',
            'portfolio_positions.*.integer' => 'Portfolio positions must be numbers between 1 and 12.',
        ];
    }
}
