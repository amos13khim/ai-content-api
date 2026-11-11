<?php

declare(strict_types=1);

namespace App\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class UsageRepository
{
    /** @return array{total: int, succeeded: int, failed: int, pending: int, input_tokens: int, output_tokens: int, attempts_with_unreported_usage: int} */
    public function aggregate(int $userId, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $generations = DB::table('generations')->where('user_id', $userId)->where('created_at', '>=', $from)->where('created_at', '<', $until);
        $counts = (clone $generations)->selectRaw("COUNT(*) AS total, COALESCE(SUM(status = 'succeeded'), 0) AS succeeded, COALESCE(SUM(status = 'failed'), 0) AS failed")->first();
        // Aggregate attempts separately: joining them into the counts would multiply generations.
        $usage = DB::table('generation_attempts as a')->joinSub((clone $generations)->select('id'), 'g', 'g.id', '=', 'a.generation_id')
            ->selectRaw('COALESCE(SUM(a.input_tokens), 0) AS input_tokens, COALESCE(SUM(a.output_tokens), 0) AS output_tokens, COALESCE(SUM(a.input_tokens IS NULL OR a.output_tokens IS NULL), 0) AS unknown_usage')->first();

        return [
            'total' => (int) $counts->total, 'succeeded' => (int) $counts->succeeded, 'failed' => (int) $counts->failed,
            'pending' => (int) $counts->total - (int) $counts->succeeded - (int) $counts->failed,
            'input_tokens' => (int) $usage->input_tokens, 'output_tokens' => (int) $usage->output_tokens,
            'attempts_with_unreported_usage' => (int) $usage->unknown_usage,
        ];
    }
}
