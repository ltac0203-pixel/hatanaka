# Git commit guidelines

Japanese version: [commit-guidelines.ja.md](./commit-guidelines.ja.md)

This document defines the commit granularity and message conventions used in this project.

## Commit-granularity principle

### One commit = one intent

A commit is a unit of change with a single, clear purpose.

### Size guidelines

| Range     | Recommendation | Notes                                       |
| --------- | -------------- | ------------------------------------------- |
| ~200 LOC  | Ideal          | Easy to review                              |
| ~400 LOC  | Acceptable     | Consider splitting if you go beyond this    |
| 400+ LOC  | Split          | Exceptions: generated files, bulk reformat  |

## Practical rules for splitting commits

### Rule 1: Cut along "revertable" boundaries

Make each commit something you could naturally revert in isolation: "I want to revert just this commit" should be a sensible thing to do.

```
❌ Mix UI tweaks and an API contract change in the same commit
⭕ Put UI tweaks and API contract changes in separate commits
```

### Rule 2: Separate "feature" from "preparation"

```
⭕ Good (split)
1. Add routing (the page scaffold)
2. Add API client (just the call)
3. Implement the display logic
4. Add tests

❌ Bad (everything mashed together)
1. New page + new API + logic + tests in one commit
```

### Rule 3: Don't mix refactors with behavior changes

- Keep refactors (renames, reorganization, extraction) separate from spec changes.
- This makes it easier later to answer "which change broke this?".

### Rule 4: Formatter / linter changes go in their own commit

Bulk reformatting from Prettier / ESLint / Pint **must not be mixed with other changes**. Otherwise the meaningful diff drowns in formatting noise and review becomes painful.

```
⭕ style: format with Pint     (standalone commit)
⭕ feat: add user search       (separate commit)

❌ feat: add user search + formatting fixes
```

## Commit-message conventions

> **Note:** `public/build/` is excluded via `.gitignore`. Build artifacts should not be committed.

### Prefixes

| Prefix       | Use                                                  |
| ------------ | ---------------------------------------------------- |
| `feat:`      | New feature                                          |
| `fix:`       | Bug fix                                              |
| `refactor:`  | Refactor (no behavior change)                        |
| `docs:`      | Documentation-only change                            |
| `style:`     | Formatting-only change (no semantic effect on code)  |
| `test:`      | Add or update tests                                  |
| `chore:`     | Build / tooling changes                              |

## Example: when to split

When a change has multiple intents, splitting like this is recommended:

```
⚠️ This change covers several intents. Suggested split:

Commit 1: style: format with Pint
  - Formatting changes under backend/Controllers/*.php

Commit 2: feat: add user search API
  - Search method in backend/Controllers/UserController.php
  - API client in src/api/user.ts

Commit 3: feat: add user search UI
  - src/pages/UserSearch.tsx
  - src/components/user/SearchForm.tsx
```

## Adjusting commit size

### When a commit is too large

- Multiple intents mixed together → run `git reset HEAD~` and split them.
- A diff that grew in scope mid-work → stage in chunks (`git add -p`) and commit them separately.

### When commits are too small

- A series of small typo fixes → consider squashing.
- Use `git rebase -i HEAD~N` to combine commits.

## Notes

- Unstaged changes are never committed automatically.
- Never commit `.env` or files containing credentials.
- Match the prevailing commit-message style in the repository (Japanese or English).
- If a commit feels **too big**, split it.
- If commits feel **too small** (e.g. three typo fixes in a row), squash them.
