---
description: Standards for README files and all project documentation in PHP libraries.
paths:
  - "**/*.md"
---

# Documentation

## README

1. Include an anchor-linked table of contents.
2. Start with a concise one-line description of what the library does.
3. Include a **badges** section (license, build status, coverage, latest version, PHP version).
4. Provide an **Overview** section explaining the problem the library solves and its design philosophy.
5. **Installation** section: Composer command (`composer require vendor/package`).
6. **How to use** section: complete, runnable code examples covering the primary use cases. Each example
   includes a brief heading describing what it demonstrates.
7. If the library exposes multiple entry points, strategies, or container types, document each with its own
   subsection and example.
8. **License** and **Contributing** sections at the end.
9. Write strictly in American English. See `rules/code-style.md` American English section for spelling conventions.

## Structured data

1. When documenting constructors, factory methods, or configuration options with more than 3 parameters,
   use tables with columns: Parameter, Type, Required, Description.
2. Prefer tables to prose for any structured information.

## Style

1. Keep language concise and scannable.
2. Never include placeholder content (`TODO`, `TBD`).
3. Code examples must be syntactically correct and self-contained.
4. Do not document `Internal/` classes or private API. Only document what consumers interact with.
