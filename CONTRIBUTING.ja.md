[English](./CONTRIBUTING.md) / 日本語

# コントリビュートガイド

本プロジェクトはリファレンス／スターターテンプレートです。正確性・可読性・セキュリティ・開発体験を改善する PR を歓迎します。

## 基本姿勢

- Issue・PR・レビューでは敬意を保ってください。意見の相違は健全ですが、敵意ある言動はお断りします。
- 影響の大きい変更（新機能・破壊的 API 変更）は先に Issue を立ててください。小さな修正は直接 PR で構いません。
- 日本市場向けの決済ゲートウェイ（Fincode）を対象としているため、Issue・PR は日本語でも英語でも構いません。

## 開発フロー（GitHub Flow）

1. `main` からブランチを切ります。ブランチ名は `feature/<短い名前>` または `bugfix/<短い名前>`。
2. [コミットガイドライン](./docs/architecture/commit-guidelines.ja.md) に沿ってコミットを作成します。
3. ブランチを push すると、CI（`.github/workflows/ci.yml`）が `main` 向けの Draft PR を自動作成します。
4. 準備が整ったら **Ready for review** に切り替えます。レビュー承認後は **Squash merge**（`main` の履歴を線形に保つため）。
5. **`main` への直接 push は禁止**。必ず PR を経由してください。

`main` は常にデプロイ可能な状態を保ちます。`release` / `develop` のような長命ブランチは作成しません。

## ローカル開発

詳細は [docs/getting-started/local-development.ja.md](./docs/getting-started/local-development.ja.md) を参照。要約：

```bash
composer setup   # 依存関係インストール、.env作成、マイグレーション、アセットビルド
composer dev     # php server + queue:listen + pail + vite を並走起動
```

## テスト・Lint

PR は以下をすべて通す必要があります：

```bash
composer test      # PHPUnit 11
./vendor/bin/pint  # PHP コード整形（Pint）
npm run lint       # ESLint
npm run test:run   # Vitest
```

詳細は [docs/getting-started/testing.ja.md](./docs/getting-started/testing.ja.md)。

Fincode API に触れるロジックを追加する場合は、**`Http::fake()` またはサービス層モック**を優先してください。テストモードであっても**実 Fincode API は叩かない**方針です（理由は [testing.ja.md](./docs/getting-started/testing.ja.md) 参照）。

## コードスタイル

- PHP: [Laravel Pint](https://laravel.com/docs/pint) のデフォルト設定。コミット前に `./vendor/bin/pint` を実行。
- TypeScript / React: ESLint。`npm run lint`（自動修正は `npm run lint -- --fix`）。
- フォーマット用コミットはロジック変更と**分離**します。[commit-guidelines.ja.md](./docs/architecture/commit-guidelines.ja.md) のルール 4 を参照。

```
⭕ feat: ユーザー検索を追加                  （ロジック）
⭕ style: pintでフォーマット                 （整形のみ・別コミット）

❌ feat: ユーザー検索追加 + 全体フォーマット （混在。レビュー不能）
```

## コミットメッセージ

[docs/architecture/commit-guidelines.ja.md](./docs/architecture/commit-guidelines.ja.md) のプレフィックスに従ってください：

| プレフィックス | 用途 |
| --- | --- |
| `feat:` | 新機能 |
| `fix:` | バグ修正 |
| `refactor:` | リファクタ（挙動変更なし） |
| `docs:` | ドキュメントのみ |
| `style:` | フォーマットのみ |
| `test:` | テストのみ |
| `chore:` | ビルド・ツールチェイン |

## pre-commit フック（推奨）

```bash
git config core.hooksPath .githooks
chmod +x .githooks/pre-commit scripts/check-secrets.sh   # macOS / Linux
```

`scripts/check-secrets.sh --staged` が `.env` や `m_test_*` / `m_prod_*` のキー誤コミットを検知します。Windows では `chmod` 不要。

## Fincode 認証情報

テストは実 Fincode キーに依存しないでください。プレースホルダか `Http::fake()` を使用します。実キーは絶対にコミットしないでください（pre-commit フックが検知しますが、最終責任は提出者にあります）。

CI は Fincode 認証情報を保持していません。前提として依存しないでください。

## セキュリティ

脆弱性を発見した場合、**公開 Issue は作成しないでください**。[SECURITY.ja.md](./SECURITY.ja.md) の手順に従ってください。

## ライセンス

PR を提出することで、貢献内容が [Apache License 2.0](./LICENSE) のもとで公開されることに同意したものとみなします。
