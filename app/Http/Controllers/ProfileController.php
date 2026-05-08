<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\FincodeApiException;
use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\SubscriptionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
    ) {}

    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit');
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());
        $request->user()->save();

        return redirect()->route('profile.edit');
    }

    public function destroy(DeleteAccountRequest $request): RedirectResponse
    {
        try {
            $this->subscriptionManager->cancelAllForUserAndDelete($request->user());
        } catch (FincodeApiException) {
            return redirect()->route('profile.edit')
                ->with('error', '退会処理に失敗しました。時間をおいて再試行してください。');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
