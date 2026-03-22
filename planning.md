# Betting Analytics — Planning

## Contexto

Nuevo proyecto de análisis y estadísticas de apuestas deportivas. Obtiene datos de partidos desde
football-data.org, los procesa y genera señales de apuesta basadas en estadísticas históricas de la
temporada. Misma base técnica que BetProject.

---

## Stack técnico

| Capa | Tecnología |
|------|-----------|
| Lenguaje | PHP 8.4 |
| Framework | Symfony 8.0 |
| ORM | Doctrine ORM 3.x + Migrations 4.x |
| Base de datos | PostgreSQL 16 |
| Tests | PHPUnit 13 |
| CI/CD | GitHub Actions |
| Contenedores | Docker (nginx, php-fpm, php-cli, postgres, postgres_test) |

---

## Arquitectura — DDD

```
src/
├── Domain/           → entidades, interfaces de repo, servicios de dominio puros
├── Application/      → servicios de aplicación, DTOs
└── Infrastructure/   → repos Doctrine, cliente HTTP, controllers, commands
```

### Convenciones de código (heredadas de BetProject)
- Constructor **privado** + factory method estático `create(...): self`
- Getters **sin prefijo** `get`: `name()`, `status()`, nunca `getName()`
- `declare(strict_types=1)` en todos los ficheros PHP
- Sin docblocks ni comentarios salvo lógica no evidente
- Migraciones en `src/Infrastructure/Shared/Persistence/Doctrine/Migrations/`

### Testing
- Nomenclatura: `test_{acción}_{contexto}__should_{resultado_esperado}`
- Unitarios: mockear libremente, probar una sola clase
- Integración: **solo se mockea la API externa**, la BD de tests es real
- `docker compose exec -T php-cli php bin/phpunit`

---

## Infraestructura Docker

```
nginx          → puerto 8080
php-fpm        → sirve la app
php-cli        → comandos Symfony
postgres       → BD principal · puerto 5432
postgres_test  → BD de tests  · puerto 5433
```

---

## Fuente de datos

**API:** football-data.org — variable de entorno `FOOTBALL_DATA_API_KEY`

**Liga inicial:** Primera División española (`PD`). Arquitectura multi-liga desde el diseño:
añadir una nueva liga = ejecutar `SeedSeasonCommand` con otro código de competición.
Solo temporada actual. Al acabar la temporada se borran equipos y partidos; se conservan estadísticas
de apuestas a largo plazo (a definir en fases posteriores).

---

## Modelo de datos — Fase 1

### `Competition`
Representa una liga de la que hacemos seguimiento completo.

| Campo | Tipo | Notas |
|-------|------|-------|
| id (UUID interno) | uuid | |
| code | string | `PD`, `PL`, `BL1`... código de football-data.org |
| name | string | "Primera División", "Premier League"... |

### `Team`
| Campo | Tipo | Notas |
|-------|------|-------|
| id (UUID interno) | uuid | |
| external_id | int | |
| name | string | |
| competition | FK Competition | liga a la que pertenece |

### `LeagueMatch` _(partidos de liga — datos completos)_
> `match` es palabra reservada en PHP 8, por eso `LeagueMatch`.

| Campo | Tipo | Notas |
|-------|------|-------|
| id (UUID interno) | uuid | |
| external_id | int | |
| competition | FK Competition | |
| home_team | FK Team | |
| away_team | FK Team | |
| matchday | int | jornada |
| played_at | datetime | fecha/hora del partido |
| status | string | SCHEDULED / FINISHED / ... |
| home_goals_ft | int\|null | resultado final local |
| away_goals_ft | int\|null | resultado final visitante |
| home_goals_ht | int\|null | descanso local |
| away_goals_ht | int\|null | descanso visitante |

> Los campos `_ht` son necesarios para los criterios **Over 0.5 HT** y **Win Both Halves**.

### `NonLeagueMatch` _(partidos no ligueros — solo contexto)_
Registra si un equipo tiene un partido fuera de su liga de seguimiento (copa, europa, etc.)
que pueda afectar su rendimiento. **Un registro por equipo participante**, aunque ambos sean
de la misma liga. El criterio de inclusión es el tipo de competición, no el día de la semana
(un partido de liga también puede jugarse entre semana y no va aquí).

| Campo | Tipo | Notas |
|-------|------|-------|
| id (UUID interno) | uuid | |
| external_id | int | ID del partido en football-data.org |
| team | FK Team | equipo de liga afectado |
| played_at | datetime | cuándo se juega |
| status | string | SCHEDULED / FINISHED |
| competition_name | string | "Copa del Rey", "UEFA Champions League"... |

> No se guardan resultados, solo la existencia del partido y su estado.

### `SyncState`
Una fila por competición. Permite saber si ya se sincronizó hoy sin repetir llamadas a la API.

| Campo | Tipo | Notas |
|-------|------|-------|
| id (UUID) | uuid | |
| competition | FK Competition | |
| last_synced_at | datetime\|null | null = nunca sincronizado |

---

## Tipos de apuesta a calcular _(fase posterior)_

Los mismos que BetProject:

| Criterio | Descripción |
|----------|-------------|
| HomeWin | Gana el local |
| AwayWin | Gana el visitante |
| DoubleChance | 1X / X2 / 12 |
| BTTS | Ambos equipos marcan |
| CleanSheetHome | El local no encaja |
| Over 0.5 HT | Al menos 1 gol en el primer tiempo |
| Over 1.5 | Más de 1.5 goles en el partido |
| Over 2.5 | Más de 2.5 goles |
| Over 3.5 | Más de 3.5 goles |
| Under 2.5 | Menos de 2.5 goles |
| WinBothHalves | El equipo gana los dos tiempos |

---

## Estrategia de sincronización

### Filosofía
Guardamos **todos los partidos de la temporada desde el inicio** (incluyendo futuros con estado
SCHEDULED). Así, incluso si no se visita la web durante varias jornadas, sabemos exactamente qué
partidos necesitan actualizarse.

### Flujo inicial — "Seed"
Un command (`SeedSeasonCommand`) ejecutado una sola vez al inicio de temporada, parametrizable por liga:
1. Fetch equipos de la competición → persiste en `teams` vinculados a su `competition`
2. Fetch todos los fixtures de liga de la temporada → persiste en `league_matches` con status SCHEDULED
3. Fetch fixtures no ligueros de cada equipo (copa, europa...) → persiste en `non_league_matches`

### Sincronización diaria — "Sync"
Disparada automáticamente **una vez al día** cuando el usuario entra en la web (síncrona,
el dashboard espera la respuesta antes de cargar):

1. Comprueba `SyncState.last_synced_at`. Si es de hoy → no hace nada.
2. Si no, dos queries:
   - `league_matches` con `status = SCHEDULED` y `played_at < NOW()` → fetch resultados completos (FT + HT)
   - `non_league_matches` con `status = SCHEDULED` y `played_at < NOW()` → solo actualizar status a FINISHED
3. Llama a la API solo para los registros encontrados → actualiza y cierra
4. Actualiza `SyncState.last_synced_at = now()`

**Ejemplo:** si la última visita fue en la jornada 10 y ahora es la 14:
- Jornadas 1–10 ya tienen `status = FINISHED` → no se tocan
- Jornadas 11–14 tienen `status = SCHEDULED` y `played_at` en el pasado → se sincronizan

No hay que recordar en qué jornada te quedaste. La BD lo sabe por el estado de cada partido.

### Trigger en web + loading
1. Usuario visita `/` → recibe página mínima con spinner CSS
2. Esa página lanza `fetch('/sync')` via JavaScript
3. `GET /sync` ejecuta `SyncService::sync()` de forma síncrona y devuelve `{"status":"ok"}`
4. Cuando el fetch resuelve, JavaScript redirige a `/dashboard`
5. `/dashboard` es el placeholder hasta la fase de apuestas

---

## Bounded Contexts

- **Tracking** → `Competition`, `Team`, `LeagueMatch`, `NonLeagueMatch`, `SyncState`, cliente HTTP, commands
- **Betting** → criterios de apuesta, estadísticas _(fase posterior)_

---

## Fuera de scope — Fase 1

- Lógica de apuestas (criterios, estadísticas, señales)
- Vistas Twig (se añadirán cuando haya apuestas)
- Soporte multi-liga activo (el diseño lo permite, pero solo se seedea LaLiga por ahora)
