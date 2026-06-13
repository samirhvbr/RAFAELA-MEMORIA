<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class GameController extends Controller
{
    /**
     * Serve a SPA do jogo e injeta a configuração dos níveis para o JS.
     */
    public function index(): View
    {
        $levels = [
            ['id' => 1, 'rows' => 2, 'cols' => 2, 'label' => '2×2'],
            ['id' => 2, 'rows' => 2, 'cols' => 3, 'label' => '2×3'],
            ['id' => 3, 'rows' => 4, 'cols' => 4, 'label' => '4×4'],
            ['id' => 4, 'rows' => 4, 'cols' => 5, 'label' => '4×5'],
            ['id' => 5, 'rows' => 6, 'cols' => 6, 'label' => '6×6'],
            ['id' => 6, 'rows' => 6, 'cols' => 8, 'label' => '6×8'],
            ['id' => 7, 'rows' => 8, 'cols' => 8, 'label' => '8×8'],
        ];

        return view('game.index', compact('levels'));
    }
}
