# Jogo da Memória da Rafaela — Diretrizes de Segurança

**Versão:** 1.0
**Data:** 2026-06-12
**Responsável pelos dados:** Samir Hanna Verza
**Objetivo:** Garantir que segurança seja prioridade máxima em todas as decisões
de código e arquitetura, adaptado à realidade deste projeto.

> Este documento é a **fonte única** de princípios de segurança do projeto.

---

## Sumário

1. [Princípio Fundamental](#1-princípio-fundamental)
2. [Modelo de Ameaças](#2-modelo-de-ameaças)
3. [Regras Gerais (obrigatórias)](#3-regras-gerais-obrigatórias)
4. [Vulnerabilidades e Como Evitá-las](#4-vulnerabilidades-e-como-evitá-las)
5. [Login do Admin (caso específico)](#5-login-do-admin-caso-específico)
6. [Segredos e Configuração](#6-segredos-e-configuração)
7. [Logs e Auditoria](#7-logs-e-auditoria)
8. [Rate Limiting e Throttling](#8-rate-limiting-e-throttling)
9. [Tratamento de Erros](#9-tratamento-de-erros)
10. [LGPD — Dados de uma criança](#10-lgpd--dados-de-uma-criança)
11. [Manutenção de Dependências](#11-manutenção-de-dependências)
12. [Resposta a Incidentes](#12-resposta-a-incidentes)
13. [Checklist Rápido (pré-commit)](#13-checklist-rápido-pré-commit)

---

## 1. Princípio Fundamental

> **Segurança > Produtividade > Performance > Facilidade**

Qualquer decisão de arquitetura, código ou biblioteca passa primeiro pelo filtro
de segurança. Se algo for inseguro, **não será aceito**, mesmo que seja mais
rápido ou mais fácil.

**Princípios derivados:**

- **Menor privilégio.** Todo código e processo opera com o mínimo necessário.
- **Fail-closed.** Em erro ou ausência de configuração, o sistema **nega**.
  Sem `ADMIN_PASSWORD_HASH` configurado, o login admin sempre falha.
- **Defesa em profundidade.** Validação no front (UX) + Form Request + constraint
  no banco. Nunca confiar em uma camada só.
- **Não confiar em entrada.** Toda entrada — body do `POST /api/log`, parâmetros
  de filtro do admin, headers HTTP — é tratada como hostil.

---

## 2. Modelo de Ameaças

A aplicação tem **duas superfícies**: um jogo público anônimo (`/`, `/api/log`)
e um painel admin protegido (`/admin/*`).

| Ator | Vetor típico | Mitigação principal |
|---|---|---|
| **Bot / script** | Spam de `POST /api/log`, inflar/poluir estatísticas | `throttle` na rota, validação rígida, CSRF |
| **Atacante externo** | Brute-force no login do admin | Rate limit por IP+e-mail, hash de senha, mensagens genéricas |
| **Scanner de vulnerabilidades** | Acesso a `.env`, `.git`, `storage/logs` | Bloqueio no vhost Nginx, `root` no `public/` |
| **Erro humano em deploy** | `APP_DEBUG=true` em prod, senha real commitada | `.gitignore`, checklist, hash no `.env` |
| **Injeção de payload** | XSS via user-agent gravado e exibido no painel | Escaping `{{ }}` no Blade, validação de tamanho |

Ao desenhar uma feature nova, pergunte: **qual desses atores tem incentivo de
atacar este fluxo, e como seria barrado?**

> ⚠️ Atenção especial: o painel admin **exibe** `ip_address` e `user_agent`
> vindos do cliente. Esses valores são entrada hostil e **devem** ser exibidos
> sempre com escaping `{{ }}` — nunca `{!! !!}`.

---

## 3. Regras Gerais (obrigatórias)

- Princípio de menor privilégio em todo o código.
- Nunca confie em nenhuma entrada do usuário/cliente.
- Valide, sanitize e escape **em todas as camadas**.
- Use Eloquent / Query Builder parametrizado em **100%** das queries.
- Nunca concatene strings em SQL, comandos shell, HTML ou headers.
- Todo output para o navegador é escapado com `{{ }}`.
- **Fail-closed** em qualquer decisão de autorização.
- **Nunca** logar dados sensíveis (senha, hash, tokens).
- **Nunca** commitar `.env`, hash de senha real, chaves ou credenciais.

---

## 4. Vulnerabilidades e Como Evitá-las

### 4.1 SQL Injection

- **Sempre** Eloquent ou Query Builder parametrizado.
- Os filtros do dashboard (`level`, `date_from`, `date_to`) passam por
  `where()`/`whereDate()` parametrizados — nunca concatenação.
- Evitar `whereRaw()`. Se inevitável: bind explícito `->whereRaw('col = ?', [$v])`.

### 4.2 XSS (Cross-Site Scripting)

- **Nunca** `{!! $var !!}` com dado de cliente (especialmente `user_agent`).
- Escaping automático do Blade (`{{ $var }}`) é o padrão.
- Dados do servidor para o JS do jogo: `@json($levels)` / `csrf_token()` —
  nunca interpolar variável crua dentro de `<script>`.
- CSP restritivo nos headers do Nginx (ver `docs/DEPLOY.md`).

### 4.3 CSRF (Cross-Site Request Forgery)

- `@csrf` em **todos** os formulários (login, logout, limpar logs).
- `POST /api/log` envia o token no header `X-CSRF-TOKEN` (não excluir do CSRF).
- Middleware de verificação de CSRF sempre ativo para rotas web.

### 4.4 Mass Assignment

- `GameLog` tem `$fillable` **explícito**.
- `ip_address` e `user_agent` são definidos **no servidor**
  (`$request->ip()`, `$request->userAgent()`), **nunca** vindos do body — mesmo
  que o cliente envie, são ignorados.

### 4.5 Validação de Input

- `POST /api/log` validado por `StoreGameLogRequest`: tipos, faixas
  (`level` 1–7, valores `>= 0`), `in:` para `status` e `score`, `max:` em strings.
- Login validado por `AdminLoginRequest`.
- Nunca confiar apenas em validação JavaScript.

### 4.6 Outras Proteções

- **Headers de Segurança**: HSTS, X-Frame-Options, X-Content-Type-Options,
  Referrer-Policy, CSP — configurados no vhost Nginx.
- **Comparação de segredos**: `hash_equals()` / `Hash::check()`. Nunca `==`/`===`.
- **Tokens aleatórios**: `Str::random(64)` / `bin2hex(random_bytes(32))` quando
  necessário.

---

## 5. Login do Admin (caso específico)

O roteiro original (`docs/`) compara e-mail e senha em **texto puro** vindos do
`.env`. **Substituímos** por uma abordagem endurecida:

- `.env` guarda `ADMIN_EMAIL` e `ADMIN_PASSWORD_HASH` (hash bcrypt/argon, **não**
  a senha em claro).
- A verificação usa `Hash::check($input, config('admin.password_hash'))` e
  `hash_equals` para o e-mail.
- **Rate limit** no `POST /admin/login` (ver §8). Excedido → 429.
- Login bem-sucedido: `session()->regenerate()` antes de marcar a flag.
- Logout: `session()->invalidate()` + `session()->regenerateToken()`.
- Mensagem de erro **genérica** ("Credenciais inválidas") — sem distinguir
  e-mail inexistente de senha errada.
- Sem configuração de hash → login **sempre nega** (fail-closed).

Gerar o hash:

```bash
php artisan tinker --execute="echo Hash::make('SUA_SENHA');"
```

---

## 6. Segredos e Configuração

- **`.env` nunca é commitado.** Está no `.gitignore`.
- **`.env.example` é a fonte da verdade das chaves esperadas.** Toda variável
  nova entra com placeholder no `.env.example` no mesmo commit que a usa.
- **Nunca** colocar segredo (hash de senha real, `APP_KEY`) em log, mensagem de
  erro, response ou comentário.
- **`APP_KEY`** rotacionável apenas em incidente confirmado (invalida sessões).

---

## 7. Logs e Auditoria

### O que deve ser logado

- Login do admin bem-sucedido e falho (com IP e user-agent).
- Logout do admin.
- Ações destrutivas no painel (limpar registros, exportar CSV).

### O que **nunca** pode aparecer em log

- Senha do admin (em claro ou hash).
- Token de sessão ou CSRF.
- Trechos do `.env`.

### Logging Laravel

```php
\Log::warning('admin_login_failed', ['ip' => $request->ip()]);
\Log::info('admin_login_success', ['ip' => $request->ip()]);
\Log::warning('admin_logs_cleared', ['ip' => $request->ip()]);
```

Logs em `storage/logs/laravel.log` (nunca commitar; bloquear no vhost).

---

## 8. Rate Limiting e Throttling

| Rota | Limite | Chave |
|---|---|---|
| `POST /api/log` | 60/min | IP |
| `POST /admin/login` | 5/min | IP + e-mail |

Definir limiters em `AppServiceProvider::boot()` via `RateLimiter::for(...)`.
Resposta padrão ao exceder: **429 Too Many Requests** (`Retry-After`).

> O limite de `/api/log` precisa acomodar uma criança terminando vários níveis
> em sequência, mas barrar floods automatizados — 60/min é um bom equilíbrio.

---

## 9. Tratamento de Erros

- **`APP_DEBUG=false` em PROD.** Sempre.
- Stacktraces, queries e valores de `.env` **nunca** chegam ao navegador.
- Páginas de erro genéricas em `resources/views/errors/` (`404`, `419`, `429`,
  `500`) — sem detalhes técnicos.
- Mensagens de login não distinguem "e-mail não existe" de "senha errada".
- `POST /api/log` retorna JSON mínimo; o frontend ignora o corpo da resposta.

---

## 10. LGPD — Dados de uma criança

A aplicação registra, por partida: **IP, user-agent, tempo, erros, acertos,
nível, nota e `session_id`**. O IP e o user-agent são dados pessoais e a usuária
principal é **uma criança** — o cuidado é redobrado.

- **Finalidade**: acompanhamento lúdico/parental do progresso. Nada além disso.
- **Minimização**: não coletar nome, foto, geolocalização ou qualquer
  identificador adicional. O `session_id` é um UUID anônimo gerado no cliente.
- **Acesso restrito**: somente o admin (responsável) vê os registros.
- **Retenção**: registros podem ser apagados a qualquer momento pelo painel
  ("Limpar Registros") e via exportação CSV para arquivamento offline.
- **Sem compartilhamento** com terceiros. Sem analytics externo, sem cookies de
  rastreio de terceiros.
- **Logs**: não logar o IP junto de dados que reidentifiquem a criança além do
  necessário para auditoria do admin.

Responsável pelos dados: **Samir Hanna Verza** (pai/responsável).

---

## 11. Manutenção de Dependências

- **`composer audit`** — rodar antes de cada deploy. `high`/`critical` bloqueia.
- **`npm audit`** — idem para assets de produção.
- **`composer.lock` e `package-lock.json` versionados.**
- **Updates major** (Laravel 11 → 12) nunca em hotfix — planejar janela e testar.
- **Patches** entram com revisão normal + smoke test.

---

## 12. Resposta a Incidentes

1. **Conter.** `php artisan down`.
2. **Revogar.** Trocar `ADMIN_PASSWORD_HASH`; limpar sessões.
3. **Rotar segredos.** `APP_KEY` somente em incidente grave (invalida sessões).
4. **Snapshot do banco.** `mariadb-dump` da hora do incidente, guardado à parte.
5. **Examinar logs.** `storage/logs/laravel.log` — IPs anômalos, floods em
   `/api/log`, tentativas de login em massa.
6. **Comunicar.** Notificar o responsável (Samir).
7. **Postmortem.** `.md` em `docs/postmortems/` com timeline, causa raiz, ações.
8. **Patch.** Corrigir e, se virar regra, atualizar este documento.

---

## 13. Checklist Rápido (pré-commit)

- [ ] Queries usam binding (Eloquent/Query Builder)?
- [ ] Outputs escapados com `{{ }}` (inclusive `user_agent` no dashboard)?
- [ ] Validação via Form Request (`/api/log` e login)?
- [ ] `@csrf` em todo form; `/api/log` com `X-CSRF-TOKEN` no header?
- [ ] Rate limit em `/api/log` e no login admin?
- [ ] `ip_address`/`user_agent` definidos no servidor, fora do `$fillable` do input?
- [ ] Senha do admin como **hash** no `.env` (nunca em claro, nunca commitada)?
- [ ] Dados sensíveis **não** estão sendo logados?
- [ ] `.env.example` atualizado com variável nova?
- [ ] `APP_DEBUG` continua `false` em PROD?

---

**Responsável:** Samir Hanna Verza
