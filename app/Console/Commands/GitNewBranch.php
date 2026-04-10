<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class GitNewBranch extends Command
{
    protected $signature = 'git:new-branch';

    protected $description = 'Create a new branch off the latest dev using the project branch-naming convention';

    /**
     * @var array<string, array{description: string, example: string}>
     */
    private const PREFIX_HINTS = [
        'feature' => ['description' => 'new user-facing functionality', 'example' => 'feature/account-user-management'],
        'fix' => ['description' => 'bug fix', 'example' => 'fix/login-redirect'],
        'ops' => ['description' => 'tooling, CI, infra, dev workflow', 'example' => 'ops/git-push-command'],
        'refactor' => ['description' => 'internal restructure, no behavior change', 'example' => 'refactor/user-service'],
        'test' => ['description' => 'test-only changes', 'example' => 'test/auth-coverage'],
        'docs' => ['description' => 'documentation only', 'example' => 'docs/readme-update'],
        'chore' => ['description' => 'misc maintenance, dep bumps', 'example' => 'chore/bump-deps'],
    ];

    public function handle(): int
    {
        if ($this->workingTreeDirty()) {
            $this->error('Working tree has uncommitted changes. Commit or stash them before running git:new-branch.');

            return self::FAILURE;
        }

        $fetch = $this->runStreamed('git fetch origin dev');

        if ($fetch->failed()) {
            $this->error("Failed to fetch 'dev' from origin. Does origin have a 'dev' branch?");

            return self::FAILURE;
        }

        if (! $this->localDevExists()) {
            $create = Process::path(base_path())->run('git branch dev origin/dev');

            if ($create->failed()) {
                $this->error("Failed to create local 'dev' tracking origin/dev.");

                return self::FAILURE;
            }
        }

        $checkoutDev = $this->runStreamed('git checkout dev');

        if ($checkoutDev->failed()) {
            $this->error("Failed to checkout 'dev'.");

            return self::FAILURE;
        }

        $pull = $this->runStreamed('git pull --ff-only origin dev');

        if ($pull->failed()) {
            $this->error("Local 'dev' could not be fast-forwarded to origin/dev. Resolve the divergence manually and try again.");

            return self::FAILURE;
        }

        $prefix = select(
            label: 'Branch prefix',
            options: $this->prefixOptions(),
            hint: 'Pick the category that best describes the work.',
        );

        $slug = text(
            label: 'Branch slug (kebab-case, no prefix)',
            required: true,
            validate: fn (string $value) => GitPush::validateBranchName($prefix.'/'.$value),
            hint: "Final name will be: {$prefix}/<your-slug>",
        );

        $fullName = "{$prefix}/{$slug}";

        $checkoutNew = $this->runStreamed("git checkout -b {$fullName}");

        if ($checkoutNew->failed()) {
            $this->error("Failed to create branch '{$fullName}'. Does it already exist?");

            return self::FAILURE;
        }

        $this->info("Created '{$fullName}' off dev. Run php artisan git:push when ready.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function prefixOptions(): array
    {
        $options = [];

        foreach (GitPush::prefixes() as $prefix) {
            $hint = self::PREFIX_HINTS[$prefix] ?? null;
            $options[$prefix] = $hint === null
                ? $prefix
                : "{$prefix} — {$hint['description']} (e.g. {$hint['example']})";
        }

        return $options;
    }

    private function workingTreeDirty(): bool
    {
        $result = Process::path(base_path())->run('git status --porcelain');

        return $result->failed() || trim($result->output()) !== '';
    }

    private function localDevExists(): bool
    {
        return Process::path(base_path())
            ->run('git show-ref --verify --quiet refs/heads/dev')
            ->successful();
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
