# Errors & Exceptions

- `ModelNotFoundException`: thrown by `findOrFail`/`firstOrFail`; message includes model and table, and ID when provided.
- `MassAssignmentException`: thrown when writing attributes not fillable (includes model, table, fillable, guarded lists).
- `LazyLoadingViolationException`: accessing an unloaded relation when lazy loading is prevented.
- `RelationNotFoundException`: relation name not defined on the model (includes table).
- `InvalidRelationException`: relation method did not return a `Relation` instance.
- `OptimisticLockException`: update failed due to version mismatch when optimistic locking is enabled.
- `ValidationException`: optional; thrown by a bound validator when validation fails (includes error bag).

Error messages are explicit to aid DX and observability; prefer catching specific exceptions in controllers/handlers.
