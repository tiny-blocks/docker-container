# Project

PHP library (tiny-blocks). Immutable domain models, zero infrastructure dependencies in core.

## Stack

Refer to `composer.json` for the full dependency list, version constraints, and PHP version.

## Project layout

```
src/
├── <PublicInterface>.php     # Primary contract for consumers
├── <Implementation>.php      # Main implementation or extension point
├── Contracts/                # Interfaces for data returned to consumers
├── Internal/                 # Implementation details (not part of public API)
│   └── Exceptions/           # Internal exception classes
└── Exceptions/               # Public exception classes (when part of the API)
tests/
├── Models/                   # Domain-specific fixtures reused across tests
├── Mocks/                    # Test doubles for system boundaries
├── Unit/                     # Unit tests for public API
└── Integration/              # Tests requiring real external resources (when applicable)
```

See `rules/domain.md` for folder conventions and naming rules.

## Commands

- Run tests: `make test`.
- Run lint: `make review`.
- Run `make help` to list all available commands.

## Post-change validation

After any code change, run `make review` and `make test`.
If either fails, iterate on the fix while respecting all project rules until both pass.
Never deliver code that breaks lint or tests.

## Reference-first approach

Always read all rule files and reference sources before generating any code or documentation.
Never generate from memory. Read the source and match the pattern exactly.
