<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\confirm;
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
        $carryChanges = false;

        if ($this->workingTreeDirty()) {
            $carryChanges = confirm(
                label: 'Working tree has uncommitted changes. Carry them onto the new branch?',
                default: true,
                hint: 'Your changes will be stashed, then re-applied after the new branch is created.',
            );

            if (! $carryChanges) {
                $this->error('Aborted. Commit or stash your changes, then re-run git:new-branch.');

                return self::FAILURE;
            }

            $stash = Process::path(base_path())->run('git stash push -u -m "git:new-branch carry"');

            if ($stash->failed()) {
                $this->error('Failed to stash your changes. Aborting before touching any branches.');
                $this->line($stash->errorOutput() ?: $stash->output());

                return self::FAILURE;
            }
        }

        $fetch = $this->runStreamed('git fetch origin dev');

        if ($fetch->failed()) {
            $this->error("Failed to fetch 'dev' from origin. Does origin have a 'dev' branch?");
            $this->noteStashIfCarrying($carryChanges);

            return self::FAILURE;
        }

        if (! $this->localDevExists()) {
            $create = Process::path(base_path())->run('git branch dev origin/dev');

            if ($create->failed()) {
                $this->error("Failed to create local 'dev' tracking origin/dev.");
                $this->noteStashIfCarrying($carryChanges);

                return self::FAILURE;
            }
        }

        $checkoutDev = $this->runStreamed('git checkout dev');

        if ($checkoutDev->failed()) {
            $this->error("Failed to checkout 'dev'.");
            $this->noteStashIfCarrying($carryChanges);

            return self::FAILURE;
        }

        $pull = $this->runStreamed('git pull --ff-only origin dev');

        if ($pull->failed()) {
            $this->error("Local 'dev' could not be fast-forwarded to origin/dev. Resolve the divergence manually and try again.");
            $this->noteStashIfCarrying($carryChanges);

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
            $this->noteStashIfCarrying($carryChanges);

            return self::FAILURE;
        }

        if ($carryChanges) {
            $pop = $this->runStreamed('git stash pop');

            if ($pop->failed()) {
                $this->error("Created '{$fullName}', but re-applying your stashed changes hit a conflict. Your work is still safe in 'git stash' — resolve the conflict on this branch and run 'git stash drop' when done.");

                return self::FAILURE;
            }
        }

        $this->info("Created '{$fullName}' off dev. Run php artisan git:push when ready.");

        return self::SUCCESS;
    }

    private function noteStashIfCarrying(bool $carryChanges): void
    {
        if ($carryChanges) {
            $this->line("Your uncommitted changes are safe in 'git stash' (stash@{0}). Run 'git stash pop' after resolving the issue.");
        }
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
