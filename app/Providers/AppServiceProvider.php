<?php

namespace App\Providers;

use App\Listeners\AuditEventListener;
use App\Models\FincodeCard;
use App\Models\Subscription;
use App\Policies\CardPolicy;
use App\Policies\SubscriptionPolicy;
use App\Services\Fincode\FincodeApiConfigValidator;
use App\Services\Fincode\FincodeClient;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->when(FincodeClient::class)
            ->needs(ClientInterface::class)
            ->give(function (): ClientInterface {
                $caBundle = config('fincode.ca_bundle');
                $verify = (is_string($caBundle) && $caBundle !== '' && file_exists($caBundle)) ? $caBundle : true;

                return new Client([
                    'base_uri' => config('fincode.base_url'),
                    'timeout' => config('fincode.timeout', 30),
                    'connect_timeout' => config('fincode.connect_timeout', 10),
                    'verify' => $verify,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                ]);
            });
    }

    public function boot(): void
    {
        if (! app()->runningUnitTests() && ! app()->runningInConsole()) {
            $this->app->make(FincodeApiConfigValidator::class)->validateOrFail();
        }

        Vite::prefetch(concurrency: 3);
        Model::preventLazyLoading(! app()->isProduction());
        // Mass Assignment 防御の defense-in-depth: fillable に含まれない属性を Mass Assignment で渡した時、
        // dev/test では即時例外、production では Laravel が log を出すだけにとどめる (silently drop しない)。
        // 例えば Model::create($request->validated()) に user_id 等が紛れた瞬間に検知できる。
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        Event::subscribe(AuditEventListener::class);

        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(FincodeCard::class, CardPolicy::class);

        $this->configurePasswordDefaults();
        $this->configureRateLimiters();
        $this->configureAuthMail();
    }

    /**
     * 本番では 12 文字以上、英大小文字・数字を必須にし、haveibeenpwned で漏洩済みの
     * パスワードを拒否する。開発・テストでは min(8) のみに緩めて開発時の負担と
     * 外部 API への意図しないアクセス (uncompromised) を避ける。
     */
    private function configurePasswordDefaults(): void
    {
        Password::defaults(function () {
            return app()->isProduction()
                ? Password::min(12)->mixedCase()->numbers()->uncompromised()
                : Password::min(8);
        });
    }

    private function configureAuthMail(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->subject('パスワード再設定のご案内')
                ->greeting('こんにちは')
                ->line('パスワード再設定のリクエストを受け付けました。')
                ->action('パスワードを再設定する', $url)
                ->line('リンクの有効期限は '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' 分です。')
                ->line('心当たりがない場合は、このメールを破棄してください。')
                ->salutation('hatanaka');
        });
    }

    private function configureRateLimiters(): void
    {
        // 認証済みユーザーは ID、未認証は IP で識別し、読み取り系 API の濫用を抑える。
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // ログインはアカウント単位 (email) と IP を複合鍵にし、辞書攻撃と分散攻撃の両方を抑止する。
        RateLimiter::for('api-login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });
    }
}
