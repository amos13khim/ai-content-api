<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Generation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class GenerationPolicy
{
    public function view(User $user, Generation $generation): Response
    {
        return $user->id === $generation->user_id ? Response::allow() : Response::denyAsNotFound();
    }
}
