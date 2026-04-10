<?php

use App\Console\Commands\GitPush;

it('accepts valid branch names', function (string $name) {
    expect(GitPush::validateBranchName($name))->toBeNull();
})->with([
    'feature/git-push-command',
    'fix/login-redirect',
    'ops/ci-pipeline',
    'refactor/user-model',
    'test/git-push-command',
    'docs/readme-update',
    'chore/bump-deps',
    'feature/a',
    'feature/abc-123-def',
]);

it('rejects invalid branch names', function (string $name) {
    expect(GitPush::validateBranchName($name))->toBeString();
})->with([
    'empty' => '',
    'no prefix' => 'master',
    'prefix only' => 'feature',
    'empty slug' => 'feature/',
    'uppercase' => 'feature/Foo',
    'underscore' => 'feature/foo_bar',
    'whitespace' => 'feature/foo bar',
    'leading hyphen' => 'feature/-foo',
    'trailing hyphen' => 'feature/foo-',
    'double hyphen' => 'feature/foo--bar',
    'disallowed prefix' => 'wip/foo',
    'nested slash' => 'feature/foo/bar',
]);
