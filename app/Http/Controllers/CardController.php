<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\CardInUseException;
use App\Exceptions\FincodeApiException;
use App\Http\Requests\StoreCardRequest;
use App\Models\FincodeCard;
use App\Services\CardManager;
use App\Services\Fincode\FincodeConfigValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class CardController extends Controller
{
    public function __construct(
        private CardManager $cardManager,
        private FincodeConfigValidator $configValidator
    ) {}

    public function create(Request $request): Response
    {
        $config = $this->configValidator->validate();

        if (! $config['is_valid']) {
            Log::warning('Fincode card form configuration is invalid.', [
                'user_id' => $request->user()?->id,
                'base_url' => config('fincode.base_url'),
                'public_key_configured' => config('fincode.public_key') !== '',
            ]);
        }

        return Inertia::render('Card/Create', [
            'fincode_public_key' => $config['public_key'],
            'fincode_sdk_url' => $config['sdk_url'],
            'fincode_sdk_sri_hash' => $config['sri_hash'],
            'fincode_config_error' => $config['error'],
        ]);
    }

    public function store(StoreCardRequest $request): RedirectResponse
    {
        try {
            $this->cardManager->create(
                $request->user(),
                $request->validated('token'),
                $request->boolean('is_default')
            );
        } catch (FincodeApiException) {
            return redirect()->route('cards.create')
                ->withErrors([
                    'card' => 'カードの登録に失敗しました。時間をおいて再試行してください。',
                ]);
        }

        return redirect()->route('subscription.index')
            ->with('success', 'カードを登録しました。');
    }

    public function destroy(Request $request, FincodeCard $card): RedirectResponse
    {
        $this->authorize('delete', $card);

        try {
            $this->cardManager->delete($card);
        } catch (CardInUseException $e) {
            return redirect()->route('subscription.index')
                ->withErrors([
                    'card' => $e->getMessage(),
                ]);
        } catch (FincodeApiException) {
            return redirect()->route('subscription.index')
                ->withErrors([
                    'card' => 'カードの削除に失敗しました。時間をおいて再試行してください。',
                ]);
        }

        return redirect()->route('subscription.index')
            ->with('success', 'カードを削除しました。');
    }
}
