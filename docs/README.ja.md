[English](./README.md) / 日本語

# docs — ドキュメント目次

このディレクトリは hatanaka（Fincode サブスクリプションリファレンス実装）のすべての仕様・運用ドキュメントを集約しています。OSS としての主動線は **「クローン → ローカルで動作確認 → 自社サービスへ取り入れ」** です。以下のリンクから必要な情報にたどり着けます。

> プロジェクト全体の概要は [リポジトリ直下の README.ja.md](../README.ja.md) を参照してください。

## はじめての方へ（getting-started/）

クローン直後の最短ルート。ここを順に読めばローカルで動かせます。

| ドキュメント | 内容 |
| --- | --- |
| [getting-started/local-development.ja.md](./getting-started/local-development.ja.md) | PHP / Node / DB のセットアップ、`composer setup`、`composer dev` の起動、トラブルシュート |
| [getting-started/fincode-setup.ja.md](./getting-started/fincode-setup.ja.md) | Fincode テストアカウント作成、`m_test_*` / `p_test_*` キー取得、テストカード番号 |
| [getting-started/testing.ja.md](./getting-started/testing.ja.md) | PHPUnit / Vitest の実行方法、テスト用 DB、Fincode のモック方針 |

## アーキテクチャ（architecture/）

「なぜこの形になっているか」を補足する設計資料。

| ドキュメント | 内容 |
| --- | --- |
| [architecture/overview.ja.md](./architecture/overview.ja.md) | レイヤ責務、カード登録／契約のシーケンス図、キュー利用箇所 |
| [architecture/data-model.ja.md](./architecture/data-model.ja.md) | ER 図、各テーブルの設計意図、ソフトデリート／FK 挙動 |
| [architecture/error-handling.ja.md](./architecture/error-handling.ja.md) | 例外階層、Circuit Breaker、HTTP ステータスマッピング、再試行方針 |
| [architecture/commit-guidelines.ja.md](./architecture/commit-guidelines.ja.md) | コミット粒度・プレフィックス規約 |

## API リファレンス（api/）

| ドキュメント | 内容 |
| --- | --- |
| [api/README.ja.md](./api/README.ja.md) | 認証方式・エンドポイント早見表・エラー形式・Fincode との関係図 |
| [api/openapi.yml](./api/openapi.yml) | OpenAPI 3.0.3 仕様（Redocly / Swagger UI でプレビュー可） |

## カスタマイズ（customization/）

自社サービスへ取り入れる際の拡張・削減ポイント。

| ドキュメント | 内容 |
| --- | --- |
| [customization/index.ja.md](./customization/index.ja.md) | 改変してよい範囲・凍結すべき範囲、機能の削減／拡張方針 |
| [customization/webhooks.ja.md](./customization/webhooks.ja.md) | Webhook 未同梱の前提と追加実装の指針 |

## 運用（operations/）

OSS は自動デプロイを持たないため、本ディレクトリは **任意の参考資料** という位置付けです。

| ドキュメント | 内容 |
| --- | --- |
| [operations/deployment.ja.md](./operations/deployment.ja.md) | 自社環境でホスト運用する場合のチェックリスト・Nginx / Supervisor 例 |
| [operations/api-token-rotation.ja.md](./operations/api-token-rotation.ja.md) | Sanctum トークンの寿命・abilities・ローテーション運用 |
| [operations/password-reset.ja.md](./operations/password-reset.ja.md) | 標準のパスワードリセット機構を意図的に除外している経緯と復元手順 |

## リポジトリ直下の関連ファイル

- [../README.ja.md](../README.ja.md) — プロジェクト概要・最短セットアップ
- [../CONTRIBUTING.ja.md](../CONTRIBUTING.ja.md) — コントリビュート手順
- [../SECURITY.ja.md](../SECURITY.ja.md) — 脆弱性報告の窓口
- [../CODE_OF_CONDUCT.md](../CODE_OF_CONDUCT.md) — 行動規範
- [../CHANGELOG.ja.md](../CHANGELOG.ja.md) — リリースノート
- [../LICENSE](../LICENSE) — Apache-2.0 ライセンス
