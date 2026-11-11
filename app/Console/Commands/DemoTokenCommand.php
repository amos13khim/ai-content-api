<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class DemoTokenCommand extends Command
{
    protected $signature = 'demo:token {--email=demo@example.test}';

    protected $description = 'Create a local demo user and a seven-day API token';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Demo credentials can only be created in local or testing environments.');

            return self::FAILURE;
        }
        $email = (string) $this->option('email');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('A valid email is required.');

            return self::INVALID;
        }
        $user = User::query()->firstOrCreate(['email' => $email], ['name' => 'Demo reviewer', 'password' => Str::random(64)]);
        $token = $user->createToken('demo', ['generations:read', 'generations:write'], now()->addDays(7));
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
