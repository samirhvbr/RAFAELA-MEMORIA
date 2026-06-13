# Versão — Jogo da Memória da Rafaela

**Versão atual:** `0.1.2`

> Esta versão é a fonte da verdade do projeto e é lida em runtime via
> `config('app.version')` — a aplicação extrai o **primeiro número semver
> (`X.Y.Z`)** encontrado neste arquivo. Mantenha a linha **"Versão atual"**
> sempre como a primeira ocorrência de um número de versão.

---

## 1. Convenção de Versionamento (`X.Y.Z`)

Padrão semântico simplificado, herdado do guia de projetos do Samir e adaptado
para este jogo.

| Componente | Significado | Como sobe |
|---|---|---|
| **X** | Versão estável final / release público | Manual |
| **Y** | Mudança estrutural (nova área, refatoração grande, nova integração) | Manual |
| **Z** | Incremento automático e **obrigatório** | A cada entrega (ver gatilhos) |

### Gatilhos de bump automático do `Z`

Incremente o `Z` (e registre no changelog) sempre que:

- Criar uma **tela / view** nova
- Criar uma **tabela / migration** nova
- **Modificar layout** (estrutura visual de uma tela)
- **Renomear** botão, label, rota nomeada ou coluna
- Alterar **regra de negócio** do jogo (níveis, sistema de notas, validação)
- Alterar **configuração de segurança** (rate limit, CSRF, headers, auth)

> Correções de texto, comentários e formatação (`pint`) **não** exigem bump.

---

## 2. Formato de Commit Obrigatório

```
X.Y.Z - Descrição curta em português
```

Exemplo:

```bash
git commit -m "0.1.1 - Adiciona filtro por período no painel admin"
```

O bump do `version.md` entra em **um único commit** por entrega (o primeiro da
entrega). Commits adicionais da mesma entrega repetem a versão sem novo bump.

---

## 3. Changelog

> Ordem decrescente (mais recente no topo). Cada entrada lista as mudanças e os
> gatilhos que justificaram o bump.

### `0.1.2` — 2026-06-13 — Documentação de permissões do deploy

- `docs/DEPLOY.md` §6: não assume mais `www-data`/`/var/www`. Explica descobrir
  o usuário do PHP-FPM, faz `chown` de `storage/` e `bootstrap/cache/` para ele
  e roda `optimize:clear`. Corrige o erro
  `file_put_contents(.../storage/framework/views/...): Permission denied`.
- Nova linha de troubleshooting para o erro de permissão.

_Gatilho:_ alteração de configuração/documentação de deploy.

### `0.1.1` — 2026-06-13 — Correção do build de assets no deploy

- `deploy/deploy.sh` e `docs/DEPLOY.md`: usar `npm install` quando não há
  `package-lock.json` (o `npm ci` exige o lockfile) e cair para `npm ci` quando
  ele existir. Corrige o erro `EUSAGE`/`vite: not found` na primeira publicação.
- `docs/DEPLOY.md`: novas linhas de troubleshooting para esses dois erros.

_Gatilho:_ alteração de configuração de deploy.

### `0.1.0` — 2026-06-12 — Implementação inicial

Primeira entrega funcional do **Jogo da Memória da Rafaela**, implementando a
especificação de [`docs/roteiro-jogo-rafaela.md`](docs/roteiro-jogo-rafaela.md).

**Plataforma & docs**
- Esqueleto Laravel 11 (PHP 8.2) completo e pronto para deploy.
- Documentação de projeto adaptada: `README.md`, `CLAUDE.md`, `SECURITY_GUIDELINES.md`.
- Guia de deploy para **Debian 12 + Nginx + PHP-FPM + MariaDB** em `docs/DEPLOY.md`.

**Banco de dados**
- Migration `game_logs` (registro de cada partida: nível, grid, tempo, jogadas,
  erros, acertos, nota, status, IP, user-agent, session_id) — idempotente.

**Jogo (frontend)**
- Tela inicial, tela de jogo, tela de vitória de nível e tela final.
- 7 níveis de dificuldade (2×2 → 8×8), peças com emojis infantis.
- Sistema de notas S / A+ / A / B / C.
- Registro automático de cada partida via `POST /api/log` (vanilla JS + fetch).

**Painel administrativo**
- Login protegido (credenciais via `.env`, senha em **hash**, rate-limit).
- Dashboard com cards de estatísticas, filtros (nível / período), tabela paginada.
- Exportação CSV e limpeza de registros (com confirmação).

**Segurança**
- Login admin com hash de senha, `hash_equals`, rate-limiting e rotação de sessão.
- Validação via Form Requests, `$fillable` explícito, CSRF, throttle em `/api/log`.
- Headers de segurança e bloqueio de arquivos sensíveis no vhost Nginx.

**Revisão adversarial (pré-commit, parte desta entrega)**
- Nginx: `try_files $uri =404;` no bloco PHP-FPM (fecha path traversal de `.php`).
- Jogo: guarda defensiva no tabuleiro + pool de emojis ampliado para 40 (margem).
- CSS: largura do tabuleiro alinhada à spec (`min(90vw, 550px)`).

_Gatilhos:_ novas telas (jogo + login + dashboard), nova tabela (`game_logs`),
layout inicial, configuração de segurança inicial.
