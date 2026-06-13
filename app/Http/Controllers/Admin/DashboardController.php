<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'level' => ['nullable', 'integer', 'min:1', 'max:7'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $query = GameLog::query();

        if (! empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $logs = $query->latest()->paginate(50)->withQueryString();
        $stats = $this->buildStats();

        return view('admin.dashboard', compact('logs', 'stats'));
    }

    /**
     * @return array<string, int|float>
     */
    private function buildStats(): array
    {
        return [
            'total' => GameLog::count(),
            'completed' => GameLog::where('status', 'completed')->count(),
            'avg_time' => (int) round((float) (GameLog::avg('time_seconds') ?? 0)),
            'avg_errors' => round((float) (GameLog::avg('errors') ?? 0), 1),
            'max_level' => (int) (GameLog::max('level') ?? 0),
            'unique_ips' => GameLog::distinct()->count('ip_address'),
            'today' => GameLog::whereDate('created_at', today())->count(),
        ];
    }

    public function clearLogs(Request $request): RedirectResponse
    {
        Log::warning('admin_logs_cleared', ['ip' => $request->ip()]);

        GameLog::truncate();

        return back()->with('success', 'Registros apagados com sucesso.');
    }

    public function export(Request $request): StreamedResponse
    {
        Log::info('admin_logs_export', ['ip' => $request->ip()]);

        $filename = 'logs-rafaela-'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ];

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');

            // BOM para acentuação correta no Excel.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'id', 'level', 'grid', 'time_seconds', 'moves', 'errors',
                'hits', 'score', 'status', 'ip_address', 'user_agent', 'created_at',
            ]);

            GameLog::orderByDesc('id')->chunk(500, function ($logs) use ($out) {
                foreach ($logs as $log) {
                    fputcsv($out, [
                        $log->id,
                        $log->level,
                        $this->csvCell($log->grid),
                        $log->time_seconds,
                        $log->moves,
                        $log->errors,
                        $log->hits,
                        $this->csvCell($log->score),
                        $this->csvCell($log->status),
                        $this->csvCell($log->ip_address),
                        $this->csvCell($log->user_agent),
                        optional($log->created_at)->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($out);
        }, $filename, $headers);
    }

    /**
     * Neutraliza injeção de fórmula em planilhas (CSV injection).
     */
    private function csvCell(?string $value): string
    {
        $value = (string) $value;

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
