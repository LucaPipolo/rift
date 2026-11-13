---
name: address-pr-comments
description: >-
  Fetches the open review feedback on a PR, from any reviewer: inline threads, review summaries and general
  comments, on the current branch's PR by default or a specific one given via the pr argument. Evaluates each
  point, fixes the valid ones (committing, never pushing), and drafts plain replies for every point.
when_to_use: >-
  Use to work through a PR's outstanding review feedback, whether it comes from Codex or from a person. Only
  reachable via the /address-pr-comments slash command; never invoke this on your own.
argument-hint: [pr] [reviewer]
disable-model-invocation: true
context: fork
model: sonnet
arguments: [pr, reviewer]
allowed-tools: Bash(gh *) Bash(git branch *)
---

# Addressing PR review comments

Review feedback comes from anyone: the Codex bot (`chatgpt-codex-connector`), maintainers and other contributors.
Treat them all the same way. `$reviewer` limits the run to one GitHub login when it is given; otherwise every
reviewer except the PR's own author is in scope.

## 1. Find the PR and check the branch

```!
REPO=$(gh repo view --json nameWithOwner -q .nameWithOwner)
PR="$pr"
if [ -z "$PR" ]; then
  PR=$(gh pr view --json number -q .number 2>/dev/null)
fi
if [ -z "$PR" ]; then
  echo "No PR found for the current branch, and no PR number was given. Stop and ask which PR to target."
else
  HEAD_REF=$(gh pr view "$PR" --repo "$REPO" --json headRefName -q .headRefName 2>/dev/null)
  AUTHOR=$(gh pr view "$PR" --repo "$REPO" --json author -q .author.login 2>/dev/null)
  CURRENT=$(git branch --show-current)
  echo "Owner: ${REPO%/*}"
  echo "Repo name: ${REPO#*/}"
  echo "PR: $PR"
  echo "PR author: $AUTHOR"
  echo "Reviewer filter: ${reviewer:-none, every reviewer}"
  if [ -z "$HEAD_REF" ]; then
    echo "Could not find PR #$PR in $REPO. Stop and report that."
  elif [ "$HEAD_REF" = "$CURRENT" ]; then
    echo "Head branch matches this checkout ($CURRENT). OK to fix code here."
  else
    echo "MISMATCH: PR #$PR's branch ($HEAD_REF) isn't checked out here ($CURRENT). Stop and tell the user to check out $HEAD_REF first, rather than fixing anything."
  fi
fi
```

`$pr` and `$reviewer` are the arguments of `/address-pr-comments <number> <login>`, and empty strings when left
out. Stop here on either stop message above: the next steps read and fix the code in this checkout, so a missing
PR or a branch mismatch both mean proceeding would be wrong.

## 2. Collect every piece of feedback

Using the owner, repo and PR resolved above, fetch all three kinds in one query:

```bash
gh api graphql -f query='
  query($owner: String!, $repo: String!, $number: Int!) {
    repository(owner: $owner, name: $repo) {
      pullRequest(number: $number) {
        reviewThreads(first: 100) {
          nodes {
            isResolved
            comments(first: 50) {
              nodes { databaseId author { login } body path line url }
            }
          }
        }
        reviews(first: 100) { nodes { author { login } state body url } }
        comments(first: 100) { nodes { author { login } body url createdAt } }
      }
    }
  }' -f owner="<owner>" -f repo="<repo>" -F number="<PR>"
```

Keep feedback whose author is not the PR author, matches `$reviewer` when one was given, and is not a status
message: skip `github-actions` coverage reports and Codex's own summary and state comments, which carry no
findings of their own.

- **Inline threads:** each unresolved thread is one piece of feedback. A thread marked resolved still counts when
  its reviewer comment has no reply and the code it points at is unchanged: someone resolved it without
  addressing it. List those separately in the report rather than skipping them silently.
- **Review summaries and general comments:** these have no resolved state. Split each into its separate points
  (a "Blocking" list, nits, suggested snippets) and treat every point on its own. A point the PR author already
  answered in a later comment is done.

## 3. Evaluate each point on its current merits

Read the point against the code as it stands now, not as the reviewer saw it: the branch may have moved since.
Decide plainly whether it still holds. Verify a factual claim before accepting or rejecting it, such as a tool's
output format or a framework's behaviour: run the cheapest check that settles it. A reviewer's suggested snippet is
a starting point, not an instruction; check it against the installed version and this repo's rules
(`.claude/rules/`), which win when they disagree.

## 4. If it's valid, fix it, then commit, never push

Make the code change, with a test that fails without it when the point flags a bug. Then run `make lint`,
`make sniff`, `make test` and `make test:coverage`; a fix that leaves any of them red is not done. Then decide how
to commit it, per this repo's git guidelines (`.claude/guidelines/git.md`):

- If the fix genuinely belongs with a commit already on this branch, fix it up directly:
  `git commit --fixup=<sha>`. The target commit must already exist on the current branch; never fix up a commit
  reachable only through `main` or another branch or worktree.
- Otherwise, hand off to the `git-commit` skill to write a proper new commit.

Never push. Pushing is the user's call, not this skill's.

## 5. Draft a reply for every point

Write each reply in plain, non-technical language, addressed to the actual point raised, never a generic
dismissal: what changed, or why it doesn't apply, in a sentence or two. Thank a person for the review once, not
on every point.

- **Inline thread:** one reply on that thread.
- **Review summary or general comment:** one reply covering all its points, as a short bullet per point, opening
  with `in reply to [your comment](<url>)` so the reviewer can find what it answers.

Don't post anything yet. Hand the drafts to the user, each labelled with the comment it answers (file and line,
or the comment's opening words, plus its URL): fixes are unpushed at this point, so a reply describing a fix would
point at code the reviewer can't see. When the user asks you to post them, reply on an inline thread with:

```bash
gh api "repos/<owner>/<repo>/pulls/<PR>/comments/<databaseId>/replies" -f body="the reply"
```

`<databaseId>` is the reviewer comment's own `databaseId` from step 2, not the thread's. A reply to a review
summary or general comment is a new PR comment: `gh pr comment <PR> --body "the reply"`.

## 6. Report, and leave resolving to the human

End with one table: each point, its decision (fixed, already fixed, or not applying, with the reason), and the
commit that fixed it. Then the reply drafts, and the resolved-but-unaddressed threads from step 2. Fixing the code
or replying never marks a thread resolved; that stays a manual step in the GitHub UI.
