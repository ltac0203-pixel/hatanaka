<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCardRequest;
use App\Http\Resources\CardResource;
use App\Models\FincodeCard;
use App\Services\CardManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CardController extends Controller
{
    public function __construct(
        private CardManager $cardManager
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FincodeCard::class);

        return CardResource::collection($request->user()->fincodeCards()->get());
    }

    public function store(StoreCardRequest $request): JsonResponse
    {
        $card = $this->createCardFromRequest($request);

        return response()->json([
            'data' => new CardResource($card),
        ], 201);
    }

    public function destroy(Request $request, FincodeCard $card): JsonResponse
    {
        $this->authorize('delete', $card);

        $this->cardManager->delete($card);

        return response()->json(['message' => 'カードを削除しました。']);
    }

    private function createCardFromRequest(StoreCardRequest $request): FincodeCard
    {
        $validated = $request->validated();

        return $this->cardManager->create(
            $request->user(),
            $validated['token'],
            $request->boolean('is_default')
        );
    }
}
