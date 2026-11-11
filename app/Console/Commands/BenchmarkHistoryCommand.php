<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BenchmarkHistoryCommand extends Command
{
    protected $signature = 'demo:benchmark {--rows=10000 : Number of additional synthetic generations}';

    protected $description = 'Seed a dedicated benchmark user and compare actual MySQL history query plans';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Benchmark fixtures are only available locally.');

            return self::FAILURE;
        }
        $rows = filter_var($this->option('rows'), FILTER_VALIDATE_INT);
        if ($rows === false || $rows < 1000 || $rows > 1000000) {
            $this->error('--rows must be between 1000 and 1000000.');

            return self::INVALID;
        }
        $user = User::query()->firstOrCreate(['email' => 'benchmark@example.test'], ['name' => 'Benchmark', 'password' => Str::random(64)]);
        $input = json_encode(['product_name' => 'Benchmark', 'features' => ['Synthetic'], 'language' => 'en', 'tone' => 'neutral'], JSON_THROW_ON_ERROR);
        for ($offset = 0; $offset < $rows; $offset += 500) {
            $batch = [];
            for ($i = $offset; $i < min($offset + 500, $rows); $i++) {
                $id = (string) Str::ulid();
                $createdAt = now()->subSeconds(($i * 7919) % (90 * 86400));
                $batch[] = [
                    'id' => $id, 'user_id' => $user->id, 'idempotency_key' => $id,
                    'request_hash' => hash('sha256', $input), 'input' => $input,
                    'status' => $i % 5 === 0 ? 'failed' : 'succeeded', 'provider' => 'fake', 'model' => 'benchmark',
                    'created_at' => $createdAt, 'updated_at' => $createdAt, 'completed_at' => $createdAt,
                ];
            }
            DB::table('generations')->insert($batch);
        }
        DB::statement('ANALYZE TABLE generations');
        $this->info('Benchmark generations: '.DB::table('generations')->where('user_id', $user->id)->count());
        foreach (['Optimizer choice' => '', 'Comparison without history indexes' => 'IGNORE INDEX (generations_history_index, generations_status_history_index)'] as $label => $hint) {
            $this->info($label);
            $plan = DB::select("EXPLAIN ANALYZE SELECT * FROM generations {$hint} WHERE user_id = ? AND status = ? ORDER BY created_at DESC, id DESC LIMIT 20", [$user->id, 'succeeded']);
            foreach ($plan as $line) {
                $this->line((string) array_values((array) $line)[0]);
            }
        }

        return self::SUCCESS;
    }
}
