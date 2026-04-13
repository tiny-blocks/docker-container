---
description: Domain modeling rules for PHP libraries — folder structure, naming, value objects, exceptions, enums, and SOLID.
paths:
  - "src/**/*.php"
---

# Domain modeling

Libraries are self-contained packages. The core has no dependency on frameworks, databases, or I/O.
Refer to `rules/code-style.md` for the pre-output checklist applied to all PHP code.

## Folder structure

```
src/
├── <PublicInterface>.php         # Primary contract for consumers
├── <Implementation>.php          # Main implementation or extension point
├── <Enum>.php                    # Public enum
├── Contracts/                    # Interfaces for data returned to consumers
├── Internal/                     # Implementation details (not part of public API)
│   ├── <Collaborator>.php
│   └── Exceptions/               # Internal exception classes
├── <Feature>/                    # Feature-specific subdirectory when needed
└── Exceptions/                   # Public exception classes (when part of the API)
```

**Public API boundary:** Only interfaces, extension points, enums, and thin orchestration classes live at the
`src/` root. These classes define the contract consumers interact with and delegate all real work to collaborators
inside `src/Internal/`. If a class contains substantial logic (algorithms, state machines, I/O), it belongs in
`Internal/`, not at the root.

The `Internal/` namespace signals classes that are implementation details. Consumers must not depend on them.
Never use `Entities/`, `ValueObjects/`, `Enums/`, or `Domain/` as folder names.

## Nomenclature

1. Every class, property, method, and exception name reflects the **domain concept** the library represents.
   A math library uses `Precision`, `RoundingMode`; a money library uses `Currency`, `Amount`; a collection
   library uses `Collectible`, `Order`.
2. Never use generic technical names: `Manager`, `Helper`, `Processor`, `Data`, `Info`, `Utils`,
   `Item`, `Record`, `Entity`, `Exception`, `Ensure`, `Validate`, `Check`, `Verify`,
   `Assert`, `Transform`, `Parse`, `Compute`, `Sanitize`, or `Normalize` as class suffixes or prefixes.
3. Name classes after what they represent: `Money`, `Color`, `Pipeline` — not after what they do technically.
4. Name methods after the operation in domain terms: `add()`, `convertTo()`, `splitAt()` — not `process()`,
   `handle()`, `execute()`, `manage()`, `ensure()`, `validate()`, `check()`, `verify()`, `assert()`,
   `transform()`, `parse()`, `compute()`, `sanitize()`, or `normalize()`.

## Value objects

1. Are immutable: no setters, no mutation after construction. Operations return new instances.
2. Compare by value, not by reference.
3. Validate invariants in the constructor and throw on invalid input.
4. Have no identity field.
5. Use static factory methods (e.g., `from`, `of`, `zero`) with a private constructor when multiple creation
   paths exist.

## Exceptions

1. Extend native PHP exceptions (`DomainException`, `InvalidArgumentException`, `OverflowException`, etc.).
2. Are pure: no formatted `code`/`message` for HTTP responses.
3. Signal invariant violations only.
4. Name after the invariant violated, never after the technical type:
   `PrecisionOutOfRange` — not `InvalidPrecisionException`.
   `CurrencyMismatch` — not `BadCurrencyException`.
   `ContainerWaitTimeout` — not `TimeoutException`.
5. Create the exception class directly with the invariant name and the appropriate native parent. The exception
   is dedicated by definition when its name describes the specific invariant it guards.

## Enums

1. Are PHP backed enums.
2. Include domain-meaningful methods when needed (e.g., `Order::ASCENDING_KEY`).

## Extension points

1. When a class is designed to be extended by consumers (e.g., `Collection`, `ValueObject`), it uses `class`
   instead of `final readonly class`. All other classes use `final readonly class`.
2. Extension point classes use a private constructor with static factory methods (`createFrom`, `createFromEmpty`)
   as the only creation path.
3. Internal state is injected via the constructor and stored in a `private readonly` property.

## Principles

- **Immutability**: all models and value objects adopt immutability. Operations return new instances.
- **Zero dependencies**: the library's core has no dependency on frameworks, databases, or I/O.
- **Small surface area**: expose only what consumers need. Hide implementation in `Internal/`.

## SOLID reference

| Principle                 | Failure signal                              |
|---------------------------|---------------------------------------------|
| S — Single responsibility | Class does two unrelated things             |
| O — Open/closed           | Adding a feature requires editing internals |
| L — Liskov substitution   | Subclass throws on parent method            |
| I — Interface segregation | Interface has unused methods                |
| D — Dependency inversion  | Constructor uses `new ConcreteClass()`      |
