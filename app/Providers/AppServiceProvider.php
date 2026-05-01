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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->when(FincodeClient::class)
            ->needs(ClientInterface::class)
            ->give(function (): ClientInterface {
                return new Client([
                    'base_uri' => config('fincode.base_url'),
                    'timeout' => config('fincode.timeout', 30),
                    'connect_timeout' => config('fincode.connect_timeout', 10),
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

        Event::subscribe(AuditEventListener::class);

        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(FincodeCard::class, CardPolicy::class);
    }
}
