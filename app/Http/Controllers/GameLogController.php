<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGameLogRequest;
use App\Models\GameLog;
use Illuminate\Http\JsonResponse;

class GameLogController extends Controller
{
    /**
     * Grava o registro de uma partida concluída.
     *
     * IP e user-agent são definidos no servidor — nunca vêm do corpo da
     * requisição (mesmo que o cliente os envie, são ignorados).
     */
    public function store(StoreGameLogRequest $request): JsonResponse
    {
        GameLog::create([
            ...$request->validated(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json(['ok' => true]);
    }
}
