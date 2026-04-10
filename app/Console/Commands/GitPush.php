<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class GitPush extends Command
{
    protected $signature = 'git:push';

    protected $description = 'Run ci:check and, if green, push the current branch to origin';

    private const BRANCH_PREFIXES = ['feature', 'fix', 'ops', 'refactor', 'test', 'docs', 'chore'];

    public function handle(): int
    {
        $branch = $this->currentBranch();

        if ($branch === null) {
            $this->error('Could not determine current git branch.');

            return self::FAILURE;
        }

        $this->info("Current branch: {$branch}");

        if ($this->workingTreeDirty()) {
            $this->error('Working tree has uncommitted changes. Commit or stash them before running git:push.');

            return self::FAILURE;
        }

        if (in_array($branch, ['master', 'dev', 'main'], true)) {
            $this->warn("You are on '{$branch}'. A new branch is required before pushing.");

            $newBranch = text(
                label: 'New branch name',
                required: true,
                validate: fn (string $value) => self::validateBranchName($value),
            );

            $checkout = $this->runStreamed("git checkout -b {$newBranch}");

            if ($checkout->failed()) {
                $this->error("Failed to create branch '{$newBranch}'.");

                return self::FAILURE;
            }

            $branch = $newBranch;
        }

        $this->info('Running composer run ci:check...');
        $ci = $this->runStreamed('composer run ci:check');

        if ($ci->failed()) {
            $this->error('CI checks failed. Branch is NOT ready to push. Fix the issues and rerun.');

            return self::FAILURE;
        }

        $this->info("CI checks passed. Branch '{$branch}' is ready to push.");

        if (! confirm(label: "Push '{$branch}' to origin?", default: false)) {
            $this->line('Skipped push.');

            return self::SUCCESS;
        }

        $push = $this->runStreamed("git push -u origin {$branch}");

        if ($push->failed()) {
            $this->error('git push failed.');

            return self::FAILURE;
        }

        $this->info("Pushed '{$branch}' to origin.");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    public static function prefixes(): array
    {
        return self::BRANCH_PREFIXES;
    }

    public static function validateBranchName(string $value): ?string
    {
        $prefixes = implode('|', self::BRANCH_PREFIXES);
        $pattern = '/^('.$prefixes.')\/[a-z0-9]+(-[a-z0-9]+)*$/';

        if (preg_match($pattern, $value) === 1) {
            return null;
        }

        return 'Branch name must look like <prefix>/<kebab-name>, where <prefix> is one of: '
            .implode(', ', self::BRANCH_PREFIXES).'.';
    }

    private function currentBranch(): ?string
    {
        $result = Process::path(base_path())->run('git rev-parse --abbrev-ref HEAD');

        if ($result->failed()) {
            return null;
        }

        $branch = trim($result->output());

        return $branch === '' ? null : $branch;
    }

    private function workingTreeDirty(): bool
    {
        $result = Process::path(base_path())->run('git status --porcelain');

        return $result->failed() || trim($result->output()) !== '';
    }

    private function runStreamed(string $command): ProcessResult
    {
        return Process::path(base_path())
            ->forever()
            ->run($command, function (string $type, string $buffer): void {
                $this->output->write($buffer);
            });
    }
}
