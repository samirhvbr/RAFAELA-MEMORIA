@extends('layouts.admin')

@section('content')
<div class="dash">

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- ─── Estatísticas ─────────────────────────────────────── --}}
    <section class="stats">
        <div class="stat"><span class="stat__n">{{ $stats['total'] }}</span><span class="stat__l">Partidas</span></div>
        <div class="stat"><span class="stat__n">{{ $stats['completed'] }}</span><span class="stat__l">Concluídas</span></div>
        <div class="stat"><span class="stat__n">{{ $stats['today'] }}</span><span class="stat__l">Hoje</span></div>
        <div class="stat"><span class="stat__n">{{ gmdate('i:s', min((int) $stats['avg_time'], 3599)) }}</span><span class="stat__l">Tempo médio</span></div>
        <div class="stat"><span class="stat__n">{{ $stats['avg_errors'] }}</span><span class="stat__l">Erros médios</span></div>
        <div class="stat"><span class="stat__n">{{ $stats['max_level'] }}</span><span class="stat__l">Nível máx.</span></div>
        <div class="stat"><span class="stat__n">{{ $stats['unique_ips'] }}</span><span class="stat__l">IPs únicos</span></div>
    </section>

    {{-- ─── Filtros + ações ──────────────────────────────────── --}}
    <section class="toolbar">
        <form method="GET" action="{{ route('admin.dashboard') }}" class="filters">
            <select name="level">
                <option value="">Todos os níveis</option>
                @for ($i = 1; $i <= 7; $i++)
                    <option value="{{ $i }}" @selected((string) request('level') === (string) $i)>Nível {{ $i }}</option>
                @endfor
            </select>
            <input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="Data inicial">
            <input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="Data final">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">Limpar</a>
        </form>

        <div class="actions">
            <a href="{{ route('admin.logs.export', request()->only('level', 'date_from', 'date_to')) }}"
               class="btn btn-outline">Exportar CSV</a>
            <form method="POST" action="{{ route('admin.logs.clear') }}"
                  data-confirm="Apagar TODOS os registros? Esta ação não pode ser desfeita.">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Limpar Registros</button>
            </form>
        </div>
    </section>

    {{-- ─── Tabela ───────────────────────────────────────────── --}}
    <section class="table-wrap">
        <table class="logs">
            <thead>
                <tr>
                    <th>#</th><th>Data/Hora</th><th>IP</th><th>Navegador</th>
                    <th>Nível</th><th>Grid</th><th>Tempo</th><th>Jog.</th>
                    <th>Erros</th><th>Acertos</th><th>Nota</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    @php
                        $sc = strtolower(trim((string) $log->score));
                        $badge = ['s' => 's', 'a+' => 'ap', 'a' => 'a', 'b' => 'b', 'c' => 'c'][$sc] ?? 'c';
                    @endphp
                    <tr>
                        <td>{{ $log->id }}</td>
                        <td>{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $log->ip_address }}</td>
                        <td class="ua" title="{{ $log->user_agent }}">{{ \Illuminate\Support\Str::limit((string) $log->user_agent, 28) }}</td>
                        <td>{{ $log->level }}</td>
                        <td>{{ $log->grid }}</td>
                        <td>{{ gmdate('i:s', min((int) $log->time_seconds, 3599)) }}</td>
                        <td>{{ $log->moves }}</td>
                        <td>{{ $log->errors }}</td>
                        <td>{{ $log->hits }}</td>
                        <td><span class="badge badge--{{ $badge }}">{{ $log->score }}</span></td>
                        <td><span class="status status--{{ $log->status }}">{{ $log->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="empty">Nenhum registro encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="pagination-wrap">{{ $logs->onEachSide(1)->links() }}</div>
</div>
@endsection
