# パスワードリセット運用の注意

本リポジトリは Fincode 連携リファレンス実装として配布する OSS であり、Laravel 既定のパスワードリセット機構 (`password_resets` テーブル / `Password::sendResetLink` ルート) を **意図的に削除** している。

## なぜ削除されているか

リファレンス実装として最小構成にすることが目的で、ユーザー対応 (メール配信基盤、認証フロー、運用フロー) を OSS 利用者の自社環境に委ねる方針を取っているため。

## 自社サービスへ取り入れる際のリスク

リセット機構が無いまま本番運用すると、以下のいずれかが発生したときに **アカウント救済手段がない**。

- ユーザーがパスワードを失念した
- アカウントが何らかの理由でロックされた (例: 2FA 端末紛失)
- 旧スタッフ退職に伴う引き継ぎが必要になった

カード情報・サブスクリプション契約という資産が紐づくサービスのため、復旧不能になると顧客対応コストとレピュテーション損失が大きい。

## 推奨する復元/代替フロー

OSS をベースに自社サービスを構築する場合、以下のいずれかを実装すること。

### Option A: Laravel 標準のパスワードリセットを復元 (推奨)

```bash
php artisan make:migration create_password_reset_tokens_table
# Laravel 13 既定スキーマを参照: vendor/laravel/framework/src/Illuminate/Auth/Console/stubs
```

実装手順:

1. `password_reset_tokens` テーブルのマイグレーションを追加
2. `routes/auth.php` に `Route::get('forgot-password', ...)` 等のルート群を追加 (Laravel Breeze のスタブ参照)
3. `App\Http\Controllers\Auth\PasswordResetLinkController` 等のコントローラを追加
4. メール送信用の `MAIL_*` 環境変数を本番の SES / SendGrid / Mailgun 等に向ける
5. `app/Notifications/ResetPasswordNotification.php` を必要に応じてカスタムし、リセットリンクのドメイン・有効期限・メールテンプレートを自社仕様に合わせる
6. リセット成功時の監査ログを `AuditLogger` で記録 (パスワードハッシュ自体は記録しない)

### Option B: 管理者手動再発行フロー

メール配信基盤を持たない場合は、運用管理者が CLI または管理画面から個別に新パスワードを発行する。

```php
// 例: artisan command
php artisan tinker
> App\Models\User::whereEmail('user@example.com')->first()->update(['password' => Hash::make($newPassword)]);
```

このフローを採用する場合は以下を必ず整備:

- 本人確認手順の文書化 (例: 登録情報・本人確認書類の提示)
- 一時パスワードを別経路 (電話・対面) で伝達
- ユーザー初回ログイン時にパスワード変更を強制 (`password_changed_at` カラム追加 + ミドルウェアでチェック)
- 管理者操作の監査ログ記録 (誰が、いつ、どのアカウントに対して操作したか)

## 注意

OSS の更新で将来的にパスワードリセット機構が再導入される可能性がある。アップグレード時には `routes/auth.php` のマージで競合が発生し得るため、自社実装した場合はカスタム範囲をドキュメント化しておくこと。
