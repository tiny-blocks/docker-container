---
description: Pre-output checklist, naming, typing, comparisons, and PHPDoc rules for all PHP files in libraries.
paths:
  - "src/**/*.php"
  - "tests/**/*.php"
---

# Code style

Semantic code rules for all PHP files. Formatting rules (PSR-1, PSR-4, PSR-12, line length) are enforced by `phpcs.xml`
and are not repeated here. Refer to `rules/domain.md` for domain modeling rules.

## Pre-output checklist

Verify every item before producing any PHP code. If any item fails, revise before outputting.

1. `declare(strict_types=1)` is present.
2. All classes are `final readonly` by default. Use `class` (without `final` or `readonly`) only when the class is
   designed as an extension point for consumers (e.g., `Collection`, `ValueObject`). Use `final class` without
   `readonly` only when the parent class is not readonly (e.g., extending a third-party abstract class).
3. All parameters, return types, and properties have explicit types.
4. Constructor property promotion is used.
5. Named arguments are used at call sites for own code, tests, and third-party library methods (e.g., tiny-blocks).
   Never use named arguments on native PHP functions (`array_map`, `in_array`, `preg_match`, `is_null`,
   `iterator_to_array`, `sprintf`, `implode`, etc.).
6. No `else` or `else if` exists anywhere. Use early returns, polymorphism, or map dispatch instead.
7. No abbreviations appear in identifiers. Use `$index` instead of `$i`, `$account` instead of `$acc`.
8. No generic identifiers exist. Use domain-specific names instead:
   `$data` → `$payload`, `$value` → `$totalAmount`, `$item` → `$element`,
   `$info` → `$details`, `$result` → `$outcome`.
9. No raw arrays exist where a typed collection or value object is available. Use `tiny-blocks/collection`
   (`Collection`, `Collectible`) instead of raw `array` for any list of domain objects. Raw arrays are acceptable
   only for primitive configuration data, variadic pass-through, or interop at system boundaries.
10. No private methods exist except private constructors for factory patterns. Inline trivial logic at the call site
    or extract it to a collaborator or value object.
11. Members are ordered: constants first, then constructor, then static methods, then instance methods. Within each
    group, order by body size ascending (number of lines between `{` and `}`). Constants and enum cases, which have
    no body, are ordered by name length ascending.
12. Constructor parameters are ordered by parameter name length ascending (count the name only, without `$` or type),
    except when parameters have an implicit semantic order (e.g., `$start/$end`, `$from/$to`, `$startAt/$endAt`),
    which takes precedence. The same rule applies to named arguments at call sites.
    Example: `$id` (2) → `$value` (5) → `$status` (6) → `$precision` (9).
13. No O(N²) or worse complexity exists.
14. No logic is duplicated across two or more places (DRY).
15. No abstraction exists without real duplication or isolation need (KISS).
16. All identifiers, comments, and documentation are written in American English.
17. No justification comments exist (`// NOTE:`, `// REASON:`, etc.). Code speaks for itself.
18. `// TODO: <reason>` is used when implementation is unknown, uncertain, or intentionally deferred.
    Never leave silent gaps.
19. All class references use `use` imports at the top of the file. Fully qualified names inline are prohibited.
20. No dead or unused code exists. Remove unreferenced classes, methods, constants, and imports.
21. Never create public methods, constants, or classes in `src/` solely to serve tests. If production code does not
    need it, it does not exist.

## Naming

- Names describe **what** in domain terms, not **how** technically: `$monthlyRevenue` instead of `$calculatedValue`.
- Generic technical verbs (`process`, `handle`, `execute`, `mark`, `enforce`, `manage`, `ensure`, `validate`,
  `check`, `verify`, `assert`, `transform`, `parse`, `compute`, `sanitize`, `normalize`) **should be avoided**.
  Prefer names that describe the domain operation.
- Booleans use predicate form: `isActive`, `hasPermission`, `wasProcessed`.
- Collections are always plural: `$orders`, `$lines`.
- Methods returning bool use prefixes: `is`, `has`, `can`, `was`, `should`.

## Comparisons

1. Null checks: use `is_null($variable)`, never `$variable === null`.
2. Empty string checks on typed `string` parameters: use `empty($variable)`, never `$variable === ''`.
3. Mixed or untyped checks (value may be `null`, empty string, `0`, or `false`): use `empty($variable)`.

## American English

All identifiers, enum values, comments, and error codes use American English spelling:
`canceled` (not `cancelled`), `organization` (not `organisation`), `initialize` (not `initialise`),
`behavior` (not `behaviour`), `modeling` (not `modelling`), `labeled` (not `labelled`),
`fulfill` (not `fulfil`), `color` (not `colour`).

## PHPDoc

- PHPDoc is restricted to interfaces only, documenting obligations and `@throws`.
- Never add PHPDoc to concrete classes.
