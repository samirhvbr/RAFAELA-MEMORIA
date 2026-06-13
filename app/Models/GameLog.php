<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameLog extends Model
{
    /**
     * Atributos atribuíveis em massa.
     *
     * `ip_address` e `user_agent` constam aqui para serem gravados pelo
     * controller, mas são SEMPRE definidos no servidor ($request->ip() /
     * userAgent()) — nunca a partir do corpo da requisição.
     *
     * @var list<string>
     */
    protected $fillable = [
        'level',
        'grid',
        'time_seconds',
        'moves',
        'errors',
        'hits',
        'score',
        'status',
        'ip_address',
        'user_agent',
        'session_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'time_seconds' => 'integer',
            'moves' => 'integer',
            'errors' => 'integer',
            'hits' => 'integer',
        ];
    }
}
