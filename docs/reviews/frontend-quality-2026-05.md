# フロントエンドコード品質レビュー (2026-05)

- レビュー対象: `resources/js/` 配下の TypeScript / React 実装
- スタック: Laravel 12 + Inertia.js v3 + React 19 + TypeScript + Tailwind v4 + Vite
- コード規模: 約 80 ファイル / 約 5,100 行 (テスト含む)
- レビュー観点: **型安全性 / TS 厳格性**, **i18n / 文言の一貫性**, **パフォーマンス / React 設計**
- ブランチ: `claude/review-frontend-quality-3yBq8`

このレポートは「総評」「Findings 一覧」「修正コミット」「Future Work」の 4 セクションで構成しています。
本レビュー本体に伴う修正は同ブランチのコミットで反映済みです (Findings 表の **Status** 列参照)。

---

## 総評

全体的に **質の高いコードベース** です。特に以下が良好でした。

- **型安全な i18n**: `Leaves<T>` / `PathValue<T,P>` を使った再帰型でキー文字列をコンパイル時検証。`t("nonexistent.key")` がそのまま型エラーになります (`resources/js/i18n/index.ts:4-22`)。
- **型安全な route ヘルパ**: Ziggy の `route()` を `appRoutes` で薄くラップしマジック文字列を排除 (`resources/js/utils/routes.ts`)。
- **カスタム ESLint ルール**: スナップショット直接変更禁止 / 安定キー必須 / 自明な手動 memo 警告など、設計原則をリンタで強制 (`eslint.config.js`)。
- **Inertia 標準パターンの徹底**: `useForm` の `processing` / `errors` / `reset` を一貫利用。フォームページに不要な独自 state を増やさない作り。
- **境界の防御**: `ErrorBoundary` + `InertiaPageBoundary` でページ単位の隔離、`useFincodeSDK` ではオリジン許可リストと SRI を併用した defense-in-depth。
- **テストカバレッジ**: 複雑度の高いところ (Fincode SDK 統合、フォーム送信、エラー抽出) に集中投下されている。

一方で、以下のテーマで小〜中規模の改善余地があります。詳細は次節。

1. 型情報を捨ててしまっている箇所 (`CallableFunction`, non-null `!`, `Record<string,_>` の動的アクセス)。
2. i18n の「漏れ」: `t()` を経由しないハードコード日本語が散見される。
3. 重複ロジック: フォーム送信エラー抽出が 2 箇所に存在。
4. Inertia の `processing` フラグの上に独自送信中 state を重ねている箇所がある。

---

## Findings 一覧

凡例: **High** = 機能 / 保守性に直接影響、**Med** = 中期的な技術的負債、**Low** = 改善余地のある設計事項。

### 型安全性 / TS 厳格性

| # | Severity | 課題 | 該当 | Status |
|---|---|---|---|---|
| T1 | High | `Modal` の `onClose: CallableFunction` は型情報を破棄しており呼び出しシグネチャを失う。`() => void` に絞るべき。 | `resources/js/Components/Modal.tsx:19` | **Fixed (Commit 1)** |
| T2 | Med | `usePage().props.auth.user!` で non-null 強制 unwrap。`User \| null` の型と矛盾し、ログイン状態変化時に runtime null 参照を起こしうる。Inertia 共有 props で `auth.user` 必須にするか、Layout 側で defensive 描画にする。 | `resources/js/Layouts/AuthenticatedLayout.tsx:17` | Future work |
| T3 | Med | `cards[0].id` が空配列で `TypeError` を起こす。コメントで「呼び出し元が空でないことを保証」と書かれているが TS 上は表現されていない。`cards` の型を `[FincodeCard, ...FincodeCard[]]` にするか、親 (`Plan/Show.tsx`) で空配列分岐を担保する。 | `resources/js/Pages/Plan/Partials/SubscriptionForm.tsx:55` | Future work |
| T4 | Low | `Dropdown.Content` の `width?: "48"` は単一リテラルの union で実質意味が無い (常に `"48"`)。`width` を撤廃するか、`"48" \| "56" \| "72"` のような実用 union に拡張する。 | `resources/js/Components/Dropdown.tsx:75` | Future work |
| T5 | Low | `statusBadge` の `styles[status] \|\| ""`, `labels[status] \|\| status`: `Record<string,string>` に未知ステータスが入った場合 `undefined` だが TS は型を `string` と認識する。`Record<SubscriptionStatus, string>` に絞ってフォールバックを明示する。 | `resources/js/Pages/Subscription/Index.tsx:23-45` | Future work |
| T6 | Low | `tsconfig.json` に `noUncheckedIndexedAccess` / `exactOptionalPropertyTypes` 等の追加 strict オプションが入っていない。導入すれば T3, T5 のクラスのバグをコンパイル時に弾ける。 | `tsconfig.json` | Future work |

### i18n / 文言の一貫性

| # | Severity | 課題 | 該当 | Status |
|---|---|---|---|---|
| I1 | High | `SUBSCRIPTION_CANCEL_ERROR` / `CARD_DELETE_ERROR` が日本語ハードコードされている。同ファイル内の他文言は `t()` 経由で呼ばれており、ここだけ翻訳機構の外。 | `resources/js/Pages/Subscription/Index.tsx:18-21` | **Fixed (Commit 2)** |
| I2 | Med | `ErrorBoundary` のフォールバック UI が「アプリケーションエラー」「エラーが発生しました」「予期しないエラーが…」「再読み込み」「トップへ戻る」を直接埋め込み。i18n 化対象。 | `resources/js/Components/ErrorBoundary.tsx:64-83` | **Fixed (Commit 4)** |
| I3 | Med | `t()` がキー解決失敗時に `throw` する。型レベルでキー誤りを防いでいるとはいえ、`ja.ts` 構造を変更した瞬間に画面全体が白画面になる。dev では throw / prod ではキー文字列を返す or `console.error` で済ませるフォールバックが望ましい。 | `resources/js/i18n/index.ts:34` | Future work |
| I4 | Low | `t<K>(key: K)` の戻り値型が `PathValue<typeof ja, K>` で string 以外 (ネスト object) も返り得る。`<button>{t("foo")}</button>` のようにレンダリング前提の場所で誤って枝キーを渡すと object が描画される。string-only の overload を追加すべき。 | `resources/js/i18n/index.ts:28` | Future work |

### パフォーマンス / React 設計

| # | Severity | 課題 | 該当 | Status |
|---|---|---|---|---|
| P1 | Med | 重複した `extractSubmissionError` (SubscriptionForm) と `extractRequestErrorMessage` (utils): 違いは「無視するキー集合」だけ。util 側に `skipKeys` オプションを追加し統合する。 | `resources/js/Pages/Plan/Partials/SubscriptionForm.tsx:23-46` ↔ `resources/js/utils/extractRequestErrorMessage.ts` | **Fixed (Commit 3)** |
| P2 | Med | `SubscriptionForm` が Inertia の `processing` と独自 `isSubmitting` を二重で持ち、合成変数 `isProcessing = processing \|\| isSubmitting` を作っている。`post()` の戻りが完了するまで `processing` は true なので冗長。 | `resources/js/Pages/Plan/Partials/SubscriptionForm.tsx:62-77` | Future work (テストが `isSubmitting` 駆動の disabled を検証中。`useForm` モックの `processing` を可変化する作業を伴うため別タスク化) |
| P3 | Low | `Dropdown.Trigger` は外側オーバーレイ + `onClick` で close を実装し、`Tab` フォーカス管理や `Escape` キー対応を独自に行う必要がある。Headless UI の `Menu` を採用すれば WAI-ARIA + キーボード対応を一括で得られる。 | `resources/js/Components/Dropdown.tsx` | Future work |
| P4 | Low | `AuthenticatedLayout` の `navItems` 配列は毎レンダー再生成され、`route().current(...)` も毎レンダー実行される。現状ナビ要素は 4 件で実害無し。最適化するなら `route()` 結果を usePage 由来の値からメモ化する。 | `resources/js/Layouts/AuthenticatedLayout.tsx:22-47` | Future work |
| P5 | Low | `statusBadge` 関数内で `t()` を毎回 5 回呼ぶ。`labels` / `styles` を module スコープ定数に上げれば呼び出しは初回ロード時の 1 回で済む。 | `resources/js/Pages/Subscription/Index.tsx:23-45` | Future work |

---

## 修正コミットの内訳 (このブランチに含まれるもの)

スコープを「明確で範囲が小さく既存テストを壊さない」変更に限定しました。

1. **Commit 1 — Modal onClose 型修正 (T1)**
   `CallableFunction` を `() => void` に置換。型推論が効くようになり、誤った関数を渡すとコンパイル時に検出される。
2. **Commit 2 — Subscription/Index の i18n 統一 (I1)**
   `i18n/ja.ts` に `subscription.errors.cancelFailed` / `subscription.errors.cardDeleteFailed` を追加し、ハードコード定数を `t()` 呼び出しに置換。
3. **Commit 3 — エラーメッセージ抽出ユーティリティ統合 (P1)**
   `extractRequestErrorMessage` に `options?: { skipKeys?: ReadonlySet<string> }` を追加し、`SubscriptionForm` の重複ロジックを共通関数に集約。`fallbackMessage: null` を許すオーバーロードを追加し、フォールバック有無 2 用途を 1 関数で扱う。
4. **Commit 4 — ErrorBoundary の i18n 化 (I2)**
   `i18n/ja.ts` に `errorBoundary.*` セクションを追加し、フォールバック UI 文字列を `t()` 経由に変更。

> P2 (`SubscriptionForm` の二重 state 削除) は今回スコープから外しました。既存テスト (`SubscriptionForm.test.tsx:192`) が `isSubmitting` ベースの `disabled` を直接検証しており、撤廃には `useForm` モックの `processing` を可変化するテスト側の修正が要るためです。Future Work に移動しています。

各コミット後に `npm run lint`, `tsc --noEmit`, `npm run test:run` を通す。

---

## Future Work (今回スコープ外)

短いキャッチアップタスクとして残しておきたい項目です。

- **T2 / T3 — 共有 props と nullability の整合**: Inertia 側の `HandleInertiaRequests` で `auth.user` 必須を保証するか、`User \| null` のままで Layout を防御的に描画する。`cards: [FincodeCard, ...FincodeCard[]]` にして「空配列なら親で出し分ける」契約を型で明示する。
- **T6 — tsconfig 強化**: `noUncheckedIndexedAccess` を入れて T5 や similar pattern を一括炙り出し → 別ブランチで分割対応。
- **I3 / I4 — t() のフォールバック & overload**: `t()` の戻り型を `string` overload と `nested object` overload に分離。dev 時のみ throw、prod は keypath を返す safe-mode を追加。
- **P2 — SubscriptionForm の二重 state 削除**: `useForm` の `processing` のみで disabled を制御する構成に整理。同時にテストの `useForm` モックを「`post` 呼び出し中だけ `processing: true` を返す」可変モックに切り替える必要がある。
- **P3 — Dropdown を Headless UI Menu へ移行**: アクセシビリティとキーボード操作を一括で改善。`AuthenticatedLayout` のサイドバー / 既存メニュー両方への影響を確認した上で別タスク化。
- **P4 / P5 — micro-optimizations**: `navItems` の `useMemo` 化と `statusBadge` の table 化。実害は小さいので余力で。

---

## 検証コマンド

```bash
npm run lint        # ESLint カスタムルール (snapshot/no-direct-mutation 等) を含む
npm run test:run    # Vitest 全テスト
npx tsc --noEmit    # 型チェック
npm run build       # tsc + Vite ビルド (CI 想定)
```
