# Betting Analytics — Instrucciones para Claude

## Workflow obligatorio

Antes de implementar cualquier mejora o corrección:
1. Documentarla en `planning.md` (petición en lenguaje simple)
2. Documentar los pasos técnicos en `steps.md` (pasos detallados)
3. Esperar confirmación antes de ejecutar

### Reglas de ejecución
- Mientras se trabaja en archivos de planificación o instrucciones, **no ejecutar nada**. Esperar a que el usuario dé la orden explícita de empezar.
- Antes de hacer un commit, **avisar al usuario** y esperar su confirmación para que pueda revisar el código.

---

## Convenciones de código

### Entidades Doctrine
- Constructor **privado** + factory method estático `create(...): self`
- Getters **sin prefijo `get`**: `name()`, `status()`, `playedAt()` — nunca `getName()`
- Los setters sí mantienen el prefijo `set`

### General
- `declare(strict_types=1)` en todos los ficheros PHP sin excepción
- No añadir comentarios salvo que la lógica no sea evidente por sí sola
- No añadir docblocks ni type annotations en código que no se ha modificado

### Servicios
- Los métodos públicos principales deben ser legibles: extraer bloques de lógica a métodos privados con nombres descriptivos
- Evitar métodos privados de un solo uso trivial (no abstraer por abstraer)

### Nomenclatura — PHP reservado
- `match` es palabra reservada en PHP 8. La entidad de partido de liga se llama **`LeagueMatch`**, nunca `Match`.

---

## Arquitectura DDD — reglas de capas

```
Domain/        → entidades, interfaces de repositorio, servicios de dominio puros
Application/   → servicios de aplicación (orquestan dominio + infraestructura), DTOs
Infrastructure → implementaciones de repositorio, cliente HTTP, controllers, comandos
```

- Las interfaces viven en `Domain/`, las implementaciones en `Infrastructure/`
- Controllers registrados explícitamente en `services.yaml`
- Migraciones en `src/Infrastructure/Shared/Persistence/Doctrine/Migrations/`

---

## Testing

### Nomenclatura obligatoria
```
test_{acción}_{contexto}__should_{resultado_esperado}
```
Ejemplos:
- `test_syncing__when_already_synced_today__should_skip_and_not_call_api`
- `test_finding_pending_matches__when_match_is_scheduled_but_future__should_not_return_it`

### Reglas
- Tests unitarios: mockear libremente, probar una sola clase
- Tests de integración: **solo se mockea la API externa** (football-data.org), la BD de tests es real
- No se considera implementado un cambio hasta que los tests pasan
- Ejecutar tests con: `docker compose exec -T php-cli php bin/phpunit`

---

## Infraestructura

- PHP 8.4, Symfony 8.0, Doctrine ORM 3.x, PostgreSQL 16
- Contenedor para comandos: `docker compose exec -T php-cli`
- BD principal: puerto 5432 | BD de tests: puerto 5433
- La app corre en `http://localhost:8080`
