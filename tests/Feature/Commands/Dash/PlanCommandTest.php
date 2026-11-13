<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

/**
 * Run dash:plan and decode what it prints.
 *
 * @param  string  $project  The main checkout root.
 * @param  string  $branch  The branch.
 *
 * @return array<array-key, mixed> The plan.
 */
function planOf(string $project, string $branch): array
{
    $plan = json_decode(bufferedOutputOf('dash:plan', ['project' => $project, 'branch' => $branch]), true);
    assert(is_array($plan));

    return $plan;
}

describe('when the project can be read', function (): void {
    beforeEach(function (): void {
        $this->root = scratch() . '/myapp';
        mkdir($this->root);
        file_put_contents($this->root . '/rift.yaml', "name: myapp\ndomain: myapp.test\napp:\n  port: 80\n");
        Process::fake([
            '*--git-common-dir*' => $this->root . "/.git\n",
            '*worktree*list*' => "worktree {$this->root}\nbranch refs/heads/main\n",
            '*for-each-ref*' => "main\nfeat/old\n",
        ]);
    });

    it('prints where the worktree would go', function (): void {
        expect(planOf($this->root, 'feat/x'))->toBe([
            'branch' => 'feat/x',
            'path' => $this->root . '-feat-x',
            'domain' => 'feat-x.myapp.test',
            'composeName' => 'myapp-feat-x',
            'exists' => false,
            'error' => null,
        ]);
    });

    it('says when the branch exists already', function (): void {
        expect(planOf($this->root, 'feat/old')['exists'])->toBeTrue();
    });

    it('refuses a branch another checkout has', function (): void {
        expect(planOf($this->root, 'main')['error'])->toBe("main is already checked out at {$this->root}.");
    });

    it('prints why the branch is refused', function (): void {
        expect(planOf($this->root, 'a..b')['error'])->toBe('That is not a valid branch name.');
    });

    it('exits with success', function (): void {
        artisan('dash:plan', ['project' => $this->root, 'branch' => 'x'])->assertExitCode(0);
    });
});

describe('when the project cannot be read', function (): void {
    it('prints why, with nothing planned', function (): void {
        $root = scratch();

        expect(planOf($root, ' feat/x '))->toBe([
            'branch' => 'feat/x',
            'path' => '',
            'domain' => '',
            'composeName' => '',
            'exists' => false,
            'error' => 'No rift.yaml in this checkout. Run `rift init` to create one.',
        ]);
    });
});
