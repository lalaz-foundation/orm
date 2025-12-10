# Exceptions Cheat Sheet

| Exception | When it occurs | Message context | Typical handler |
|-----------|----------------|-----------------|-----------------|
| `MassAssignmentException` | Attribute not fillable when `enforce_fillable=true` | Model, table, attribute, fillable/guarded lists | Return 422/400; log attribute name; adjust `$fillable` or disable enforcement |
| `ModelNotFoundException` | `findOrFail` / `firstOrFail` misses | Model, table, optional ID | Return 404; avoid leaking IDs; optionally log query params |
| `LazyLoadingViolationException` | Accessing unloaded relation when lazy loading prevented | Model, relation | Enable eager loading or whitelist; return 500/422 depending on layer |
| `RelationNotFoundException` | Relation method missing | Model, table, relation name | 500 for developer error; fix relation name |
| `InvalidRelationException` | Relation method exists but doesn’t return a Relation | Model, relation | 500; ensure relation returns a Relation instance |
| `OptimisticLockException` | Version check failed during update with optimistic locking | Model, ID | 409 Conflict; prompt client to reload |
| `ValidationException` | Bound validator rejects payload | Error bag | 422 Unprocessable Entity; surface validation messages |

Database-level exceptions (PDO) propagate from the query builder/connection; wrap at your HTTP handler layer.
