# Password reset operational notes

This repository is distributed as an OSS reference implementation for Fincode integration. Laravel's default password reset machinery (`password_resets` table, `Password::sendResetLink` routes) has been **intentionally removed**.

## Why it is removed

The reference implementation is intended to be minimal. User-facing concerns (mail delivery, auth flows, support workflows) are deferred to the OSS consumer's environment.

## Risk when adopting into a production service

Without a reset path there is **no recovery option** for any of the following:

- A user forgets their password
- An account is locked for some operational reason (e.g. lost MFA device)
- A staff handover requires re-issuing credentials

Because card data and subscription contracts are bound to the account, irrecoverable accounts are expensive to support and damage trust.

## Recommended restoration / alternative

When building on top of this OSS, implement one of the following.

### Option A: Restore Laravel's standard password reset (recommended)

```bash
php artisan make:migration create_password_reset_tokens_table
# Reference the Laravel 13 default schema in
# vendor/laravel/framework/src/Illuminate/Auth/Console/stubs
```

Steps:

1. Add the `password_reset_tokens` migration
2. Re-add routes in `routes/auth.php` (`Route::get('forgot-password', ...)` etc.; see Laravel Breeze stubs)
3. Add `App\Http\Controllers\Auth\PasswordResetLinkController` and friends
4. Point production `MAIL_*` env vars to SES / SendGrid / Mailgun, etc.
5. Customize `app/Notifications/ResetPasswordNotification.php` to match your domain, link lifetime, and mail template
6. Record an audit log on a successful reset via `AuditLogger` (never log the password hash itself)

### Option B: Operator-driven manual reset

If you do not have a mail delivery pipeline, an operator can issue a new password from CLI or an admin UI.

```php
// e.g. artisan tinker
> App\Models\User::whereEmail('user@example.com')->first()->update(['password' => Hash::make($newPassword)]);
```

When you choose this flow, make sure you also have:

- A documented identity verification procedure (e.g. registered info plus a government ID)
- A secondary channel to deliver the temporary password (phone, in-person)
- Forced password change at the user's first login (add a `password_changed_at` column and check it in middleware)
- Audit logs for operator actions (who, when, against which account)

## Caveat

A future OSS update may reintroduce the password reset feature. Merging `routes/auth.php` may then conflict with your customizations, so document the scope of your local changes for upgrade time.
