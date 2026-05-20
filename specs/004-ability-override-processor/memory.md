# Feature Memory - Ability Override Processor

## Scope Notes
- Implement runtime application of DB-stored ability overrides outside Manager REST requests.
- Keep Manager REST responses on the pure registry path so `_registry` values remain unmerged.

## Relevant Durable Memory
- `includes/Main.php` remains the only hook registration surface.
- DB access for sitewide overrides goes through `AcrossAI_Sitewide_Query`.

## Open Questions
- [none]

## Watchlist
- Cache invalidation must cover save, delete, and bulk reset paths.
- Static-only processor class is a documented feature-specific deviation; do not generalize it.

## Never Store Here
- Permanent project decisions
- General bug patterns unless directly reused
- Implementation history after the feature ships
