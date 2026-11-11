<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('generations:recover')->everyMinute()->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
