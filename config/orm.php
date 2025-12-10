<?php declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Column Names
    |--------------------------------------------------------------------------
    */
    "timestamps" => [
        "enabled" => true,
        "created_at" => "created_at",
        "updated_at" => "updated_at",
    ],

    "soft_deletes" => [
        "enabled" => false,
        "deleted_at" => "deleted_at",
    ],

    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    |
    | When true, only attributes listed in $fillable are mass-assignable.
    | When false, all attributes are fillable unless guarded explicitly.
    */
    "enforce_fillable" => true,
    "mass_assignment" => [
        "throw_on_violation" => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Lazy Loading Guard
    |--------------------------------------------------------------------------
    |
    | When true, accessing unloaded relations lazily will throw an exception,
    | encouraging explicit eager loading to avoid N+1.
    */
    "lazy_loading" => [
        "prevent" => false,
        "allow_testing" => false,
        "allowed_relations" => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Date Handling
    |--------------------------------------------------------------------------
    |
    | Default timezone and format used when casting datetimes.
    */
    "dates" => [
        "timezone" => null, // null will use PHP's default timezone
        "format" => DATE_ATOM,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation (optional)
    |--------------------------------------------------------------------------
    |
    | When enabled and a ModelValidatorInterface implementation is bound,
    | models will run validation rules before save/create operations.
    */
    "validation" => [
        "enabled" => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Naming Strategy
    |--------------------------------------------------------------------------
    |
    | Control how database column names are mapped to in-memory attributes.
    | Supported values: null (use database names) or "camel" (snake_case ↔ camelCase).
    */
    "naming" => [
        "hydrate" => null,
    ],
];
