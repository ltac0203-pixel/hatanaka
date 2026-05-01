[English](./CHANGELOG.md) / 日本語

# 変更履歴

このプロジェクトの主な変更点を記録します。

フォーマットは [Keep a Changelog](https://keepachangelog.com/ja/1.1.0/) に準拠し、バージョニングは [Semantic Versioning](https://semver.org/lang/ja/) に従います。

`0.x` の間はマイナーバージョンにも破壊的変更が含まれる可能性があります。サポート対象バージョンの方針は [SECURITY.ja.md](./SECURITY.ja.md) を参照してください。

## [Unreleased]

## [0.1.0] - 2026-05-01

オープンソースのリファレンス実装としての初回公開リリース。

### 追加

- Laravel 12 + React 19 + Inertia.js + TypeScript で構築したサブスクリプション管理アプリケーション。
- Fincode 決済との統合:
  - `FincodeClient` HTTP クライアント（Bearer 認証・冪等キー・ログのセンシティブデータマスク）
  - Fincode の Customer / Card / Subscription CRUD をラップしたサービス層
  - フロントエンドでの Fincode JS トークン化（フルカード番号はサーバーに到達しない）
- Laravel Breeze + Sanctum による認証: ユーザー登録・ログイン／ログアウト・パスワードリセット・メール認証
- サブスクリプション機能: プラン一覧／詳細、カード登録・一覧・削除、契約・解約、決済履歴
- `/api/*` 配下の REST API（OpenAPI 定義は `docs/api/openapi.yml`）
- 状態変更操作の監査ログ（`audit_logs`、変更前後の値を保存）
- `plans` / `subscriptions` / `cards` のソフトデリート
- 認可 Policy（`SubscriptionPolicy`、`CardPolicy`）
- セキュリティ強化: CSP レポートエンドポイント、セキュリティヘッダミドルウェア、レート制限
- ドキュメント一式（英日併記）: 環境構築、アーキテクチャ、API、運用、テンプレート流用ガイド
- プロジェクトポリシー: `LICENSE`（Apache-2.0）、`NOTICE`、`CONTRIBUTING.md`、`SECURITY.md`
- ツール類: Pint（PHP）、ESLint（TypeScript）、PHPUnit 11、Vitest、シークレット検出 pre-commit フック
- GitHub Actions CI: シークレット混入チェック、PHP のビルド／Lint／テスト（カバレッジ閾値 50%）、`feature/*` ブランチでの Draft PR 自動作成

[Unreleased]: https://github.com/ltac0203-pixel/hatanaka/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/ltac0203-pixel/hatanaka/releases/tag/v0.1.0
