<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rewrite every estimates.interview_answers blob so any `long_tail` key
     * moves to `project_wide`. Touches soft-deleted rows too.
     */
    public function up(): void
    {
        $this->rewriteKey('long_tail', 'project_wide');
    }

    public function down(): void
    {
        $this->rewriteKey('project_wide', 'long_tail');
    }

    private function rewriteKey(string $from, string $to): void
    {
        DB::table('estimates')
            ->select('id', 'interview_answers')
            ->orderBy('id')
            ->each(function (object $row) use ($from, $to): void {
                $raw = $row->interview_answers;
                if ($raw === null) {
                    return;
                }

                $decoded = json_decode($raw, true);
                if (! is_array($decoded) || ! array_key_exists($from, $decoded)) {
                    return;
                }

                $decoded[$to] = $decoded[$from];
                unset($decoded[$from]);

                DB::table('estimates')
                    ->where('id', $row->id)
                    ->update(['interview_answers' => json_encode($decoded)]);
            });
    }
};
