# Betting Analytics — Steps de implementación

> Fase 1: estructura + importación de datos. Sin lógica de apuestas, sin Twig.

---

## STEP 1 — Infraestructura Docker + Symfony skeleton

No se usa `composer create-project`. Se crea la estructura a mano igual que BetProject
y se ejecuta `composer install`.

**Ficheros a crear:**

```
betting/
├── docker-compose.yml
├── docker/
│   ├── php/Dockerfile
│   └── nginx/default.conf
├── .env                          # POSTGRES_USER, POSTGRES_PASSWORD, POSTGRES_DB, POSTGRES_DB_TEST
├── .env.example
├── .github/workflows/ci.yml
└── app/
    ├── composer.json             # dependencias exactas, sin symfony/skeleton
    ├── phpunit.dist.xml
    ├── bin/console
    ├── public/index.php
    ├── src/
    │   ├── Kernel.php
    │   ├── Domain/
    │   ├── Application/
    │   └── Infrastructure/
    ├── tests/
    │   ├── bootstrap.php
    │   ├── Unit/
    │   └── Integration/
    └── config/
        ├── bundles.php
        ├── routes.yaml           # rutas por bounded context, igual que BetProject
        ├── services.yaml         # bindings interfaz→implementación explícitos
        ├── services_test.yaml    # mismo pero con public: true para tests
        └── packages/
            ├── doctrine.yaml     # mappings apuntando a src/Domain/, PostgreSQL 16
            ├── doctrine_migrations.yaml
            ├── framework.yaml
            └── twig.yaml
```

**`app/.env`**
```
DATABASE_URL="postgresql://app:secret@postgres:5432/betting?serverVersion=16&charset=utf8"
FOOTBALL_DATA_API_KEY=
```

**`app/.env.test`**
```
DATABASE_URL="postgresql://app:secret@postgres_test:5432/betting_test?serverVersion=16&charset=utf8"
```

**`config/services.yaml`** — patrón de BetProject:
- Autowire para `App\Domain\`, `App\Application\`, `App\Infrastructure\` (excluyendo entidades, DTOs, migraciones y controllers)
- Bindings explícitos de interfaces → implementaciones Doctrine
- Controllers registrados con `tags: ['controller.service_arguments']`

**`config/routes.yaml`** — rutas por bounded context:
```yaml
tracking_controllers:
    resource:
        path: ../src/Infrastructure/Tracking/Http/Controller/
        namespace: App\Infrastructure\Tracking\Http\Controller
    type: attribute
```

**Instalar dependencias:**
```
docker compose up -d
docker compose exec php-cli composer install
```

**Verificación:** `docker compose exec php-cli php bin/console` responde sin errores.

---

## STEP 2 — Entidades de dominio

> Bounded context: `Tracking`

#### `Competition`
```
src/Domain/Tracking/Entity/Competition.php
```
- `Competition::create(string $code, string $name): self`

#### `Team`
```
src/Domain/Tracking/Entity/Team.php
```
- `Team::create(int $externalId, string $name, Competition $competition): self`

#### `LeagueMatch`
```
src/Domain/Tracking/Entity/LeagueMatch.php
```
- `LeagueMatch::create(int $externalId, Competition $competition, Team $homeTeam, Team $awayTeam, int $matchday, \DateTimeImmutable $playedAt): self`
- `finish(int $homeGoalsFt, int $awayGoalsFt, int $homeGoalsHt, int $awayGoalsHt): void`

#### `NonLeagueMatch`
```
src/Domain/Tracking/Entity/NonLeagueMatch.php
```
- `NonLeagueMatch::create(int $externalId, Team $team, \DateTimeImmutable $playedAt, string $competitionName): self`
- `finish(): void`

#### `SyncState`
```
src/Domain/Tracking/Entity/SyncState.php
```
- `SyncState::create(Competition $competition): self`
- `markSynced(\DateTimeImmutable $at): void`
- `isSyncedToday(): bool`

#### Tests unitarios
```
tests/Unit/Domain/Tracking/Entity/CompetitionTest.php
tests/Unit/Domain/Tracking/Entity/TeamTest.php
tests/Unit/Domain/Tracking/Entity/LeagueMatchTest.php
tests/Unit/Domain/Tracking/Entity/NonLeagueMatchTest.php
tests/Unit/Domain/Tracking/Entity/SyncStateTest.php
```

Casos:

**CompetitionTest**
- `test_creating_competition__with_valid_data__should_store_code_and_name`

**TeamTest**
- `test_creating_team__with_valid_data__should_store_external_id_and_name`

**LeagueMatchTest**
- `test_creating_league_match__with_valid_data__should_have_scheduled_status`
- `test_creating_league_match__should_have_null_scores_by_default`
- `test_finishing_league_match__with_scores__should_store_all_goals_and_set_finished_status`

**NonLeagueMatchTest**
- `test_creating_non_league_match__with_valid_data__should_have_scheduled_status`
- `test_finishing_non_league_match__should_set_finished_status`

**SyncStateTest**
- `test_creating_sync_state__should_have_null_last_synced_at`
- `test_marking_synced__should_store_datetime`
- `test_is_synced_today__when_synced_today__should_return_true`
- `test_is_synced_today__when_synced_yesterday__should_return_false`
- `test_is_synced_today__when_never_synced__should_return_false`

---

## STEP 3 — Interfaces de repositorio y proveedor externo

```
src/Domain/Tracking/Repository/CompetitionRepositoryInterface.php
    → save(Competition): void
    → findByCode(string $code): ?Competition

src/Domain/Tracking/Repository/TeamRepositoryInterface.php
    → save(Team): void
    → findByExternalId(int $externalId): ?Team

src/Domain/Tracking/Repository/LeagueMatchRepositoryInterface.php
    → save(LeagueMatch): void
    → findByExternalId(int $externalId): ?LeagueMatch
    → findPendingByCompetition(Competition $competition): array   // SCHEDULED + played_at < now()

src/Domain/Tracking/Repository/NonLeagueMatchRepositoryInterface.php
    → save(NonLeagueMatch): void
    → findByExternalIdAndTeam(int $externalId, Team $team): ?NonLeagueMatch
    → findPending(): array                                        // SCHEDULED + played_at < now()

src/Domain/Tracking/Repository/SyncStateRepositoryInterface.php
    → save(SyncState): void
    → findByCompetition(Competition $competition): ?SyncState

src/Domain/Tracking/Repository/FootballDataProviderInterface.php
    → fetchTeams(string $competitionCode): array
    → fetchLeagueMatches(string $competitionCode): array
    → fetchNonLeagueMatches(int $teamExternalId): array
    → fetchLeagueMatchResult(int $matchExternalId): array
```

---

## STEP 4 — Implementaciones Doctrine

```
src/Infrastructure/Tracking/Persistence/Doctrine/DoctrineCompetitionRepository.php
src/Infrastructure/Tracking/Persistence/Doctrine/DoctrineTeamRepository.php
src/Infrastructure/Tracking/Persistence/Doctrine/DoctrineLeagueMatchRepository.php
src/Infrastructure/Tracking/Persistence/Doctrine/DoctrineNonLeagueMatchRepository.php
src/Infrastructure/Tracking/Persistence/Doctrine/DoctrineSyncStateRepository.php
src/Infrastructure/Shared/Persistence/Doctrine/Migrations/   (generadas con doctrine:migrations:diff)
```

#### Tests de integración
```
tests/Integration/Infrastructure/Tracking/DoctrineTeamRepositoryTest.php
tests/Integration/Infrastructure/Tracking/DoctrineLeagueMatchRepositoryTest.php
tests/Integration/Infrastructure/Tracking/DoctrineNonLeagueMatchRepositoryTest.php
tests/Integration/Infrastructure/Tracking/DoctrineSyncStateRepositoryTest.php
tests/Integration/IntegrationTestCase.php   (base con setup/teardown de BD)
```

Casos:

**DoctrineTeamRepositoryTest**
- `test_saving_team__should_persist_and_be_retrievable_by_external_id`
- `test_saving_team__when_already_exists__should_update_name`

**DoctrineLeagueMatchRepositoryTest**
- `test_finding_pending_matches__when_match_is_scheduled_and_past__should_return_it`
- `test_finding_pending_matches__when_match_is_scheduled_but_future__should_not_return_it`
- `test_finding_pending_matches__when_match_is_already_finished__should_not_return_it`

**DoctrineNonLeagueMatchRepositoryTest**
- `test_finding_pending__when_status_scheduled_and_past__should_return_them`
- `test_finding_pending__when_already_finished__should_not_return_them`

**DoctrineSyncStateRepositoryTest**
- `test_saving_sync_state__should_be_retrievable_by_competition`
- `test_saving_sync_state__when_updated__should_persist_new_last_synced_at`

---

## STEP 5 — Cliente HTTP football-data.org

```
src/Infrastructure/Tracking/Http/Client/FootballDataClient.php
```

Implementa `FootballDataProviderInterface`. Usa `symfony/http-client`.

#### Tests unitarios
```
tests/Unit/Infrastructure/Tracking/FootballDataClientTest.php
```

Casos (mock del HttpClient, respuestas JSON fijas):
- `test_fetching_teams__should_map_api_response_to_array_with_external_id_and_name`
- `test_fetching_league_matches__should_map_response_with_scores_and_matchday`
- `test_fetching_non_league_matches__for_a_team__should_filter_out_league_competition_matches`
- `test_fetching_non_league_matches__should_map_competition_name_and_date`
- `test_fetching_league_match_result__should_return_ft_and_ht_goals`

---

## STEP 6 — Servicios de aplicación

```
src/Application/Tracking/Service/SeasonSeedService.php
src/Application/Tracking/Service/SyncService.php
```

#### `SeasonSeedService::seed(string $competitionCode): void`
1. Fetch + upsert `Competition`
2. Fetch + upsert `Team[]`
3. Fetch + upsert `LeagueMatch[]` con status SCHEDULED
4. Para cada equipo: fetch + upsert `NonLeagueMatch[]`
5. Crea `SyncState` para la competición si no existe

#### `SyncService::sync(Competition $competition): void`
1. Busca `SyncState` → si ya sincronizó hoy, retorna
2. Busca `LeagueMatch` pendientes → fetch resultado por `external_id` → `finish()`
3. Busca `NonLeagueMatch` pendientes → `finish()`
4. Actualiza `SyncState.last_synced_at`

#### Tests de integración (API mockeada, BD real)
```
tests/Integration/Infrastructure/Tracking/SeasonSeedServiceTest.php
tests/Integration/Infrastructure/Tracking/SyncServiceTest.php
```

Casos:

**SeasonSeedServiceTest**
- `test_seeding_season__should_persist_competition`
- `test_seeding_season__should_persist_all_teams_from_api_response`
- `test_seeding_season__should_persist_all_league_matches_as_scheduled`
- `test_seeding_season__should_persist_non_league_matches_for_each_team`
- `test_seeding_season__when_called_twice__should_not_duplicate_teams`
- `test_seeding_season__when_called_twice__should_not_duplicate_matches`
- `test_seeding_season__should_create_sync_state_for_competition`

**SyncServiceTest**
- `test_syncing__when_already_synced_today__should_skip_and_not_call_api`
- `test_syncing__when_never_synced__should_sync_and_save_sync_state`
- `test_syncing__when_last_sync_was_yesterday__should_sync_and_update_sync_state`
- `test_syncing__when_no_pending_matches__should_not_call_api`
- `test_syncing__when_league_match_is_pending__should_update_scores_and_set_finished`
- `test_syncing__when_non_league_match_is_pending__should_set_finished_without_scores`
- `test_syncing__when_multiple_pending_matches__should_update_all_of_them`

---

## STEP 7 — Command Symfony

```
src/Infrastructure/Tracking/Command/SeedSeasonCommand.php
```

Uso: `php bin/console tracking:seed-season PD`

---

## STEP 8 — Controller + loading ✅

```
src/Infrastructure/Tracking/Http/Controller/DashboardController.php
    → GET /          → HTML con spinner CSS + JS fetch('/sync')
    → GET /sync      → ejecuta SyncService::sync(), devuelve {"status":"ok"}
    → GET /dashboard → 200 OK placeholder (vistas en fase de apuestas)
```

---

## STEP 9 — TeamMatchStats + criterios de apuesta + tests unitarios

> Bounded context: `Betting` — lógica de dominio pura, sin persistencia.

```
src/Domain/Betting/ValueObject/TeamMatchStats.php
src/Domain/Betting/Criterion/BetCriterionInterface.php
src/Domain/Betting/Criterion/HomeWinCriterion.php
src/Domain/Betting/Criterion/AwayWinCriterion.php
src/Domain/Betting/Criterion/DoubleChanceCriterion.php
src/Domain/Betting/Criterion/BttsCriterion.php
src/Domain/Betting/Criterion/CleanSheetHomeCriterion.php
src/Domain/Betting/Criterion/Over05HalfTimeCriterion.php
src/Domain/Betting/Criterion/Over15Criterion.php
src/Domain/Betting/Criterion/Over25Criterion.php
src/Domain/Betting/Criterion/Over35Criterion.php
src/Domain/Betting/Criterion/Under25Criterion.php
src/Domain/Betting/Criterion/WinBothHalvesCriterion.php
src/Domain/Betting/Service/TeamStatsCalculator.php
```

`TeamMatchStats` es un Value Object inmutable con todos los contadores de stats.
`TeamStatsCalculator` recibe `LeagueMatchRepositoryInterface` y calcula los stats de un
equipo para una competición a partir de partidos FINISHED.

#### Tests unitarios
```
tests/Unit/Domain/Betting/Criterion/HomeWinCriterionTest.php
tests/Unit/Domain/Betting/Criterion/AwayWinCriterionTest.php
tests/Unit/Domain/Betting/Criterion/DoubleChanceCriterionTest.php
tests/Unit/Domain/Betting/Criterion/BttsCriterionTest.php
tests/Unit/Domain/Betting/Criterion/CleanSheetHomeCriterionTest.php
tests/Unit/Domain/Betting/Criterion/Over05HalfTimeCriterionTest.php
tests/Unit/Domain/Betting/Criterion/Over15CriterionTest.php
tests/Unit/Domain/Betting/Criterion/Over25CriterionTest.php
tests/Unit/Domain/Betting/Criterion/Over35CriterionTest.php
tests/Unit/Domain/Betting/Criterion/Under25CriterionTest.php
tests/Unit/Domain/Betting/Criterion/WinBothHalvesCriterionTest.php
tests/Unit/Domain/Betting/Service/TeamStatsCalculatorTest.php
```

Cada criterio tiene 3 casos: `should_return_true_when_met`, `should_return_false_when_not_met`,
`should_return_false_when_wrong_perspective`.

`TeamStatsCalculatorTest`:
- `test_calculating_stats__should_count_home_and_away_matches_separately`
- `test_calculating_stats__should_compute_form_last5_home_as_string`
- `test_calculating_stats__should_compute_over25_counters`

---

## STEP 10 — Entidad Bet + repositorio + migración + tests de integración

```
src/Domain/Betting/Entity/Bet.php
src/Domain/Betting/Repository/BetRepositoryInterface.php
    → save(Bet): void
    → findByMatch(LeagueMatch): Bet[]
    → findPendingByCompetition(Competition): Bet[]
    → findSettledByCompetition(Competition): Bet[]
    → existsForMatchAndType(LeagueMatch, string $betType): bool
src/Infrastructure/Betting/Persistence/Doctrine/DoctrineBetRepository.php
src/Infrastructure/Shared/Persistence/Doctrine/Migrations/   (generada)
```

`Bet::create(LeagueMatch $match, string $betType, string $perspective, bool $skipped = false): self`
`Bet::settle(\DateTimeImmutable $at, bool $won): void`

#### Tests de integración
```
tests/Integration/Infrastructure/Betting/DoctrineBetRepositoryTest.php
```

Casos:
- `test_saving_bet__should_be_retrievable_by_match`
- `test_finding_pending__should_only_return_pending_bets`
- `test_finding_settled__should_only_return_won_and_lost`
- `test_exists_for_match_and_type__when_exists__should_return_true`
- `test_exists_for_match_and_type__when_not_exists__should_return_false`

---

## STEP 11 — BetGeneratorService + BetSettlementService + tests de integración

```
src/Application/Betting/Service/BetGeneratorService.php
src/Application/Betting/Service/BetSettlementService.php
```

#### `BetGeneratorService::generate(Competition $competition): void`
1. Obtener todos los `LeagueMatch` SCHEDULED de la competición
2. Para cada partido:
   a. Calcular `TeamMatchStats` de homeTeam y awayTeam
   b. Evaluar cada criterio (`perspective = home` → pasa homeStats+awayStats, `perspective = away` → al revés)
   c. Detectar conflictos: si home genera tipo X y away genera tipo Y y son contradictorios, marcar bet de away como `skipped = true`
   d. Persistir cada apuesta si no existe ya (`existsForMatchAndType`)

#### `BetSettlementService::settleAll(Competition $competition): void`
1. Buscar bets PENDING cuyo `LeagueMatch` está FINISHED
2. Para cada bet, evaluar outcome según tipo y marcador
3. `bet->settle($now, $won)`

#### Tests de integración (API mockeada, BD real)
```
tests/Integration/Infrastructure/Betting/BetGeneratorServiceTest.php
tests/Integration/Infrastructure/Betting/BetSettlementServiceTest.php
```

Casos `BetGeneratorServiceTest`:
- `test_generating_bets__when_criterion_met__should_create_pending_bet`
- `test_generating_bets__when_conflicting_home_and_away__should_skip_away_bet`
- `test_generating_bets__when_called_twice__should_not_duplicate_bets`
- `test_generating_bets__when_criterion_not_met__should_not_create_bet`

Casos `BetSettlementServiceTest`:
- `test_settling__when_over25_wins__should_mark_won`
- `test_settling__when_over25_loses__should_mark_lost`
- `test_settling__when_home_win_and_home_wins__should_mark_won`
- `test_settling__when_skipped_bet__should_still_settle_for_stats`

---

## STEP 12 — Integrar betting en SyncService + SeasonSeedService + Twig

```
src/Application/Tracking/Service/SyncService.php       (actualizar)
src/Application/Tracking/Service/SeasonSeedService.php (actualizar)
src/Infrastructure/Betting/Http/Controller/BettingController.php
    → GET /dashboard   → listado de próximos partidos con apuestas activas
    → GET /bets/history → historial con stats
    → GET /stats/{teamId} → stats detalladas por equipo
templates/
    betting/dashboard.html.twig
    betting/history.html.twig
    betting/team_stats.html.twig
    layout.html.twig
```

SyncService.sync() añade al final:
1. `BetSettlementService::settleAll($competition)`
2. `BetGeneratorService::generate($competition)`

SeasonSeedService.seed() añade al final:
3. `BetGeneratorService::generate($competition)`

#### Tests funcionales
```
tests/Functional/BettingControllerTest.php
```

Casos:
- `test_dashboard__should_list_upcoming_matches_with_active_bets`
- `test_history__should_list_finished_matches_with_outcomes`
- `test_team_stats__should_show_bet_stats_for_team`
