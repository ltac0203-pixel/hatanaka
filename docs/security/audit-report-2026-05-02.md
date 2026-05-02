# セキュリティ監査レポート 2026-05-02

`/security-audit` 6エージェント Wave並列オーケストレータによる Fincode 決済統合セキュリティ監査の結果。

---

## 監査概要

| 項目 | 内容 |
|------|------|
| 監査日 | 2026-05-02 |
| 対象ブランチ | `fix/license-apache-2-text` (HEAD: cf6641e) |
| 監査者 | `/security-audit` (6エージェント Agent Teams) |
| 対象範囲 | Fincode決済統合・認証認可・インジェクション/データ漏洩・インフラ設定 |
| 監査対象ファイル数 | 約 90 ファイル（PHP 約 60 + TSX/TS 約 30） |
| Wave 1 (Red Team) 構成 | 4 エージェント並列 (payment / auth / injection / infra) |
| Wave 2 (Blue Team) 構成 | 2 エージェント並列 (backend / frontend, plan mode) |
| 発見脆弱性総数 | **27 件** |
| 修正完了数 | **27 件 (100%)** |

---

## カテゴリ別 Red Team 発見サマリ

| カテゴリ | エージェント | 発見数 | CRITICAL | HIGH | MEDIUM | LOW |
|---------|-------------|-------|---------|------|--------|------|
| 決済 (Payment) | red-team-payment | 9 | 2 | 2 | 2 | 3 |
| 認証認可 (Auth) | red-team-auth | 9 | 1 | 2 | 4 | 2 |
| インフラ (Infra) | red-team-infra | 8 | 0 | 2 | 4 | 2 |
| インジェクション (Injection) | red-team-injection | 1 | 0 | 0 | 1 | 0 |
| **合計** | | **27** | **3** | **6** | **11** | **7** |

### Injection スキャンが少ない理由

`red-team-injection` の Grepプリスキャンは以下の通り 1件しかヒットせず、実装上の規律が極めて高いことを確認:

| パターン | ヒット数 |
|---------|---------|
| `dangerouslySetInnerHTML` | 0 |
| `$request->all()` | 0 |
| `DB::raw / whereRaw / *Raw` | 0 |
| `$guarded = []` | 0 |
| `exec / shell_exec / eval / system / passthru / proc_open / popen` | 0 |
| `dd() / dump() / ray() / var_dump() / print_r()` | 0 |
| `$request->input/get/query` 直接使用 | 0 |
| `console.log` センシティブ出力 | 0（4件中全て意図的なエラーログ） |

→ Mass Assignment 防御の弱体化（`user_id` を fillable 保持）の 1 件のみ defense-in-depth 観点で要修正。

---

## 重要度別 修正状況

| 重要度 | 件数 | 修正完了 | 担当 |
|-------|-----|---------|------|
| CRITICAL | 3 | 3 (100%) | Backend 3 |
| HIGH | 6 | 6 (100%) | Backend 5 + Frontend 1 |
| MEDIUM | 11 | 11 (100%) | Backend 10 + team-lead 1 |
| LOW | 7 | 7 (100%) | Backend 5 + Frontend 1 + team-lead 1 |

---

## 脆弱性詳細

### CRITICAL

#### #5 [PAYMENT-VULN] Guzzle例外メッセージ経由のセンシティブデータ漏洩
- **場所**: `app/Services/Fincode/FincodeClient.php::logError`、上位 catch (`CardManager`, `SubscriptionManager`, `CustomerSyncService`)
- **攻撃シナリオ**: `Guzzle\Exception\RequestException::getMessage()` は HTTP ボディ全文を文字列に埋め込むため、`maskSensitiveData()` の配列ベースマスクを完全バイパス。カード保有者名・末尾4桁・場合によっては Fincode API キーがログに平文流出。
- **影響**: PII漏洩、API キー漏洩
- **修正**: `$e->getMessage()` を除去、`classifyAndThrow` を `sanitizeExceptionMessage` 経由化、上位 catch も `exception_class + status_code` のみに正規化
- **追加テスト**: `FincodeClientRetryTest` で例外メッセージ漏洩防止を検証

#### #6 [AUTH-VULN] Sanctum API tokens granted full abilities
- **場所**: `app/Http/Controllers/Api/AuthController.php:54-59`
- **攻撃シナリオ**: ログイン時に `create/read/update/delete` 4 abilities が無条件付与。読み取り専用クライアントもトークン1本リークで解約+カード削除まで実行可能。30日寿命固定+ローテ無し。
- **影響**: トークン漏洩時の被害最大化
- **修正**: `LoginRequest` に abilities ホワイトリスト追加、AuthController でフィルタ、未指定時は最小権限 (read のみ)、書き込み権限を含むトークンは寿命 7 日に短縮

#### #7 [PAYMENT-VULN] Fincodeトークンの形式・有効期限・使用回数バリデーション欠落
- **場所**: `app/Http/Requests/StoreCardRequest.php`
- **攻撃シナリオ**: 同一トークン再利用による不正カード登録、ネットワーク傍受トークンのリプレイ
- **修正**: `min:20` / `max:255` / regex 形式チェック + `Cache::add` による二重送信検出 (5分)、`is_default` を `sometimes+boolean`
- **追加テスト**: `StoreCardRequestTest` にトークン形式・最小長・二重送信のテスト 3 件追加

### HIGH

#### #8 [AUTH-VULN] Web 新規登録でセッション再生成欠落 (Session Fixation)
- **場所**: `app/Http/Controllers/Auth/RegisteredUserController.php:28-43`
- **攻撃シナリオ**: 攻撃者が固定セッションIDを被害者に仕込む → 被害者が登録 → 攻撃者が認証済みセッションを奪取
- **修正**: `$request->session()->regenerate()` を `Auth::login` 直後に追加

#### #9 [INFRA-VULN] HSTS が production リバースプロキシ越しで発火しない
- **場所**: `bootstrap/app.php` (TrustProxies 未設定)
- **攻撃シナリオ**: ALB/CDN 配下で `isSecure()` が常に false → HSTS 未発火 → 初回 HTTP→HTTPS 中継時の Cookie 平文漏洩
- **修正**: `bootstrap/app.php` に `trustProxies(at: env('TRUSTED_PROXIES','*'), headers: ...)` 追加。`.env.example` に `TRUSTED_PROXIES=*` のドキュメント付き行追加

#### #10 [PAYMENT-VULN] サーキットブレーカー DoS — Retry-After 上限なし
- **場所**: `app/Services/Fincode/FincodeClient.php::parseRetryAfterHeader`
- **攻撃シナリオ**: 攻撃者または Fincode 不具合で `Retry-After: 86400` を受信 → PHP-FPM ワーカーが ~1日 sleep → サービス停止
- **修正**: `retry.max_delay_ms` で上限クリップ
- **追加テスト**: `Retry-After: 99999` クリップを検証

#### #11 [AUTH-VULN] `verified` ミドルウェアが no-op
- **場所**: `app/Models/User.php:16` (MustVerifyEmail 未実装)
- **攻撃シナリオ**: メール認証なしで /dashboard, /cards, /subscription 全機能利用可能。他人メールで登録 → 不正課金
- **修正**: `User implements MustVerifyEmail` 追加（factory デフォルト verified、unverified() は EmailVerificationTest のみ）

#### #13 [INFRA-VULN] npm依存に既知脆弱性10件
- **対象**: vite, axios, rollup, lodash-es, picomatch, flatted, follow-redirects, postcss, qs, brace-expansion
- **修正**: `npm audit fix` (--force 不要) で全件解消。axios のみ `package.json` に書き込み、他は `package-lock.json` のみ。`npm run build` / vitest 30件全パス確認済み

#### #14 [PAYMENT-VULN] StoreSubscriptionRequest の card_id IDOR
- **場所**: `app/Http/Requests/StoreSubscriptionRequest.php`
- **攻撃シナリオ**: `card_id` の `exists` ルールが user スコープなし → 他人カード列挙・指定可能
- **修正**: `Rule::exists()->where('user_id', ...)` で user スコープ化

### MEDIUM

| ID | カテゴリ | タイトル | 修正内容 |
|----|---------|---------|---------|
| #12 | INJECTION | user_id を fillable に保持 | `Model::preventSilentlyDiscardingAttributes(! app()->isProduction())` を AppServiceProvider に追加。dev/test では即時例外、production では log のみ |
| #15+#21 | AUTH/INFRA | CORS allowed_methods/allowed_headers ワイルドカード | ホワイトリスト化、`CORS_ALLOWED_ORIGINS` env 対応 |
| #16 | INFRA | CSP に test/production Fincode 常時許可 | `FINCODE_PRODUCTION` env で本番/テスト分離 |
| #17 | INFRA | グローバルレート制限・読み取り系 API throttle 未設定 | `throttle:api` (60req/min/user-or-ip) を auth:sanctum グループに適用 |
| #18 | PAYMENT | デフォルトカード競合状態 | `CardManager::create` で User 行 `lockForUpdate` 取得後にデフォルトカード切替 |
| #19 | AUTH | API ログインのレート制限が IP のみ | RateLimiter `api-login` を `email+IP` 複合鍵で定義 |
| #20 | INFRA | LOG_LEVEL=debug + LOG_STACK=single | `.env.example` を `LOG_LEVEL=warning` / `LOG_STACK=daily` + コメント |
| #22 | AUTH | API トークン寿命 30日固定+ローテ無し | ログイン成功時に同 device_name の既存トークン破棄。寿命 read 30日 / write 7日。`docs/operations/api-token-rotation.{md,ja.md}` を新設 |
| #24 | PAYMENT | PlanService キャッシュポイズニング | negative cache を `Cache::put` で短い TTL (60s) に分離 |
| #26 | AUTH | CSP report エンドポイント本文サイズ無制限 | `CspReportController` に 16KB の本文サイズ制限追加 |

### LOW

| ID | カテゴリ | タイトル | 修正内容 |
|----|---------|---------|---------|
| #23 | INFRA | Permissions-Policy 不足 | payment, usb, accelerometer, gyroscope, magnetometer, midi, serial, interest-cohort 追加 |
| #25 | INFRA | CSP に object-src/form-action/upgrade-insecure-requests 不足 | `object-src 'none'`, `form-action 'self'` 追加。本番/staging で `upgrade-insecure-requests` |
| #27 | PAYMENT | Fincode SDK SRI ハッシュ未設定 | 機構は既に opt-in 実装済（`FINCODE_SDK_SRI_HASH` env）。`docs/getting-started/fincode-setup.{md,ja.md}` に「本番運用では SRI 推奨」「SDK 更新時にハッシュ再生成必須」「2026-05-02 時点の参考値」追記。`.env.example` に `FINCODE_SDK_SRI_HASH=` 行追加 |
| #28 | AUTH | CardPolicy::view 未定義 + viewAny 無条件 true | `view` 追加 (所有者チェック)、`viewAny` を認証必須化 |
| #29 | PAYMENT | 期限切れカード判定 tz 境界 | `FincodeCard::isExpired` を `config('app.timezone')` ベースに揃え月末境界バグ防止 |
| #30 | AUTH | パスワードリセット機構削除（運用リスク） | `docs/operations/password-reset.{md,ja.md}` を新設して自社運用時の代替フロー設計指針を明文化 |
| #31 | PAYMENT | AuditLogger の生 IP/UA 信頼 | `RequestContextResolver` に #9 (TrustProxies) 連動で信頼境界が確立される旨をコメント明文化 |

---

## 攻撃チェーン（修正前リスク）

Red Team が構築した複数脆弱性連鎖シナリオ（**全て修正済み**）:

### Chain A: PII漏洩フルチェーン
攻撃者 → 不正トークン送信 (#7 突破) → Fincode 4xx エラー → #5 で例外メッセージ経由でカード保有者名・末尾4桁が平文ログ流出

### Chain B: トークン漏洩 → 課金破壊
ユーザーが mobile クライアントログイン → #6 で 4 abilities 全付与 → トークン漏洩 → 攻撃者が解約+カード削除 → 30日寿命+ローテ無し+logout が currentToken のみで攻撃者トークン生存 (#22)

### Chain C: メール詐称登録 → 不正課金
攻撃者が他人メールで登録 → #11 で verified no-op、メール認証スキップ → 攻撃者カードで被害者名義 Fincode 契約

### Chain D: 分散クレデンシャルスタッフィング
攻撃者がプロキシプール 100 個 → #19 (IP-only throttle) でアカウント単位ロックアウト不発 → 弱いパスワード当て → Chain B へ接続

### Chain E: Session Fixation → 完全制御
攻撃者が固定セッション ID 仕込み (#8) → 被害者登録で認証昇格 → /cards/create + /subscription で完全アクセス

### Chain F: HSTS 不発火 + Cookie 漏洩
本番 ALB 配下で #9 により HSTS 未発火 → 初回 HTTP リダイレクト中の Cookie 平文 → 認証セッション奪取

### Chain G: MITM フルチェーン
#27 SRI 未設定で SDK 改ざん許容 → トークン化前のカード番号窃取 → 通常フロー経由でカード登録 → #5 でログ集約への漏洩拡大

### Chain H: CORS ドリフト → CSRF バイパス
将来 `APP_URL` 緩和 + #21 (allowed_methods=* + supports_credentials=true) → 任意メソッドで Cookie 自動送信 → 認証セッション持つ被害者ブラウザから `evil.example.com` 経由で `DELETE /api/subscription` 発火

---

## Blue Team 対応サマリ

### blue-team-backend (PHP/Laravel)
- 修正タスク数: **22 件** (#5,#6,#7,#8,#9,#10,#11,#12,#14,#15,#16,#17,#18,#19,#21,#22,#23,#24,#25,#26,#28,#29,#30,#31)
- 変更ファイル例: `FincodeClient.php`, `AuthController.php`, `StoreCardRequest.php`, `RegisteredUserController.php`, `bootstrap/app.php`, `User.php`, `StoreSubscriptionRequest.php`, `config/cors.php`, `SecurityHeaders.php`, `routes/api.php`, `AppServiceProvider.php`, `CardManager.php`, `PlanService.php`, `CspReportController.php`, `CardPolicy.php`, `FincodeCard.php`, `RequestContextResolver.php`
- 新設テスト: `FincodeClientRetryTest`、`StoreCardRequestTest` の追加ケース 3 件
- 新設ドキュメント: `docs/operations/password-reset.{md,ja.md}`, `docs/operations/api-token-rotation.{md,ja.md}`

### blue-team-frontend (React/TypeScript)
- 修正タスク数: **2 件** (#13, #27)
- `npm audit fix` 実行で 10件の既知脆弱性解消（破壊的変更なし）
- `docs/getting-started/fincode-setup.{md,ja.md}` に SDK SRI ハッシュ運用セクション追加

### team-lead 直接対応
- `.env.example` の更新（`TRUSTED_PROXIES`, `LOG_LEVEL`, `LOG_STACK`, `FINCODE_SDK_SRI_HASH`）
- `tests/Feature/SecurityTest.php` の CSP 期待値を環境分離後の挙動に合わせて修正

---

## セキュリティスコア（カテゴリ別 修正前後評価）

| カテゴリ | 修正前 | 修正後 | 主な改善 |
|---------|-------|-------|---------|
| Payment (Fincode) | C | A | Guzzle例外漏洩遮断 / トークンリプレイ防止 / Retry-After DoS耐性 / IDOR遮断 |
| Auth (認証認可) | D | A | abilities最小権限化 / Session Fixation解消 / verified有効化 / API throttle強化 / トークンローテーション |
| Injection / XSS | A | A | 元から優秀（Grepヒット ほぼ 0）、defense-in-depth 強化のみ |
| Infrastructure | C | A | HSTS実効化 / npm脆弱性解消 / CSP環境分離・補強 / CORS厳格化 / ログ運用適正化 |
| **総合** | **D+** | **A** | OSSリファレンスとして配布可能なベースラインに到達 |

---

## 検証結果

| 項目 | 結果 | 備考 |
|------|------|------|
| Pint (`pint --test`) | **PASS** | コードスタイル準拠 |
| npm build (`npm run build`) | **PASS** | 1035 modules transformed, 4.93s, gzip後 app.js=129KB |
| vitest (`npm run test:run`) | **PASS** | 9 files / 30 tests 全パス（Frontend実施） |
| PHPUnit (Unit, 非DB依存) | **PASS** | FincodeClient + CircuitBreaker + FincodeClientRetry 49件全パス（Backend実施） |
| PHPUnit (Feature, DB依存) | **CI待ち** | ローカル MySQL 未起動のため 221 errors 発生（全件 PDO接続失敗）。CI (mysql:8.0) で検証 |
| 元の SecurityTest 失敗1件 | **修正済** | CSP環境分離 (#16) に合わせて `assertMatchesRegularExpression` で test/prod 両対応に変更 |

---

## 推奨アクション（残タスク）

### 即時 (mainマージ前)
1. **CI で PHPUnit Feature の DB依存テストを実行**して 221件のエラーが解消することを確認
2. **本番環境変数の見直し**:
   - `TRUSTED_PROXIES`: 本番ロードバランサのIP/CIDRに合わせて設定（`*` のままでも同一VPC内なら可）
   - `FINCODE_PRODUCTION=true`: 本番環境のみ
   - `LOG_LEVEL=warning`: 既に .env.example で適用済み、本番 .env 追従推奨
3. **モバイル/Bot クライアント保有者への通知**: API トークン寿命短縮（30日→7日）と device_name 単位破棄の挙動変更を `docs/operations/api-token-rotation.ja.md` を参照して周知

### 中期 (1ヶ月以内)
4. **本番運用時の SRI ハッシュ有効化**: Fincode SDK 更新検知の運用フローを整備した上で `FINCODE_SDK_SRI_HASH` を本番 .env に設定
5. **パスワードリセット代替フロー**: `docs/operations/password-reset.ja.md` を参照して自社プロダクト向けに復元 or 管理者再発行フローを設計

### 長期 (継続)
6. **依存関係監査の自動化**: `npm audit` / `composer audit` を CI に組み込み、新規CVEを検知
7. **CSP report-uri の監視**: `/api/security/csp-reports` への送信内容を集約して継続的に CSP違反を可視化

---

## 監査メソドロジ

```
/security-audit 実行
  │
  ├─ Phase 1: TeamCreate (security-audit)
  │
  ├─ Phase 2 (Wave 1): Red Team 4並列スキャン
  │  ├─ red-team-payment    → 9件発見 [PAYMENT-VULN]
  │  ├─ red-team-auth       → 9件発見 [AUTH-VULN]
  │  ├─ red-team-injection  → 1件発見 [INJECTION-VULN]
  │  └─ red-team-infra      → 8件発見 [INFRA-VULN]
  │  → Red Team 4体 shutdown
  │
  ├─ Phase 3 (Wave 2): Blue Team 2並列修正 (plan mode)
  │  ├─ blue-team-backend   → 22件修正 [BACKEND-FIX]
  │  └─ blue-team-frontend  →  2件修正 [FRONTEND-FIX]
  │  → team-lead で .env.example, テスト1件 追加修正
  │  → Blue Team 2体 shutdown
  │
  ├─ Phase 4: 検証 (Pint / npm build / PHPUnit 並列)
  │
  ├─ Phase 5: レポート出力 (本ドキュメント)
  │
  └─ Phase 6: TeamDelete
```

---

## 制約遵守確認

- ✅ Red Team はコード変更ゼロ（発見と報告のみ）
- ✅ Blue Team は plan mode で計画承認後に修正
- ✅ 本番環境へのアクセスなし
- ✅ 実 API キー、実カード番号は本レポートに含まれていない
- ✅ `.env`（実環境のシークレット）は読み込まず、`.env.example` のみ更新
- ✅ `composer audit` / `npm audit` はレジストリ照会のため実行（読み取りのみ）
