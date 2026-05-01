English / [日本語](./CONTRIBUTING.ja.md)

# Contributing

Thank you for considering a contribution. This project is a reference / starter template — patches that improve correctness, clarity, security, or developer experience are welcome.

## Ground rules

- Be respectful in issues, PRs, and reviews. Disagreement is fine; hostility is not.
- Open an issue first for non-trivial changes (new features, breaking API changes). Small fixes can go straight to a PR.
- This template targets a Japanese payment gateway (Fincode), so issues / PRs may be filed in Japanese or English. Either is fine.

## Workflow (GitHub Flow)

1. Fork or branch from `main`. Branch name: `feature/<short-name>` or `bugfix/<short-name>`.
2. Make commits following the [commit guidelines](./docs/architecture/commit-guidelines.md).
3. Push the branch. CI (`.github/workflows/ci.yml`) automatically opens a Draft PR targeting `main` for any branch named `feature/*`.
4. When ready, mark the PR as **Ready for review**. Squash merge after approval (keeps `main` history linear).
5. **Direct push to `main` is forbidden.** Always go through a PR.

`main` is always deployable. Long-lived `release` / `develop` branches are not used.

## Local development

See [docs/getting-started/local-development.md](./docs/getting-started/local-development.md) for the full setup. Short version:

```bash
composer setup   # installs deps, copies .env, runs migrations, builds assets
composer dev     # starts php server + queue:listen + pail + vite
```

## Tests and linters

PRs must pass:

```bash
composer test      # PHPUnit 11
./vendor/bin/pint  # PHP code style (Pint)
npm run lint       # ESLint
npm run test:run   # Vitest
```

Details: [docs/getting-started/testing.md](./docs/getting-started/testing.md).

When adding business logic that touches the Fincode API, prefer **mocking via `Http::fake()` or service-level fakes**. Do not hit the real Fincode API in tests, even in test mode — see [docs/getting-started/testing.md](./docs/getting-started/testing.md) for the rationale.

## Code style

- PHP: [Laravel Pint](https://laravel.com/docs/pint) defaults. Run `./vendor/bin/pint` before committing.
- TypeScript / React: ESLint. Run `npm run lint` (auto-fix with `npm run lint -- --fix`).
- Keep formatting commits **separate** from logic commits — see [commit-guidelines.md](./docs/architecture/commit-guidelines.md) Rule 4.

```
⭕ feat: add user search                     (logic)
⭕ style: pint formatting                    (formatting only — separate commit)

❌ feat: add user search + format whole tree (mixed; reviewers can't tell what changed)
```

## Commit messages

Follow the prefixes defined in [docs/architecture/commit-guidelines.md](./docs/architecture/commit-guidelines.md):

| Prefix | Use |
| --- | --- |
| `feat:` | New feature |
| `fix:` | Bug fix |
| `refactor:` | Refactor (no behavior change) |
| `docs:` | Documentation only |
| `style:` | Formatting only |
| `test:` | Tests only |
| `chore:` | Build / tooling |

## Pre-commit hook (recommended)

```bash
git config core.hooksPath .githooks
chmod +x .githooks/pre-commit scripts/check-secrets.sh   # macOS / Linux
```

`scripts/check-secrets.sh --staged` blocks accidental commits of `.env`, `m_test_*` / `m_prod_*` API keys, and similar patterns. On Windows the `chmod` step is unnecessary.

## Fincode credentials

Tests must not require real Fincode keys. Use placeholders or `Http::fake()`. Never commit real keys — the pre-commit hook will catch obvious cases, but you are the last line of defense.

CI does not have Fincode credentials and is not expected to.

## Security

If you find a security vulnerability, **do not open a public issue**. Follow [SECURITY.md](./SECURITY.md).

## License

By contributing, you agree that your contribution is licensed under the [Apache License 2.0](./LICENSE).
