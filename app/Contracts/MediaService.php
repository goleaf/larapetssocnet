<?php

namespace App\Contracts;

use App\Models\User;
use Illuminate\Http\UploadedFile;

interface MediaService
{
    public function storeUserImage(User $user, UploadedFile $file, string $collection): void;
}
