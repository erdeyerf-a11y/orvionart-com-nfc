# Architecture Decision Record — 0.3.0

## Decision
Prepare source using Laravel 12 and PHP 8.3+, with MySQL/MariaDB as target relational database, versioned migrations, server-side authorization, Blade views, and environment-based secrets.

## Why
- The platform needs mature routing, sessions, CSRF, validation, throttling, migrations, logging, queues, and maintainable modular services.
- Supplied server information confirms PHP 8.3.34 and Node.js; user reports empty live directory/database.
- A framework is preferable to hand-writing security-critical primitives.

## Status / caveat
This is a **provisional source-level decision**, not a claim that Composer, PHP extensions (including `pdo_mysql`), database services, or CLI access were verified on the actual server. Binding V1.5 instructions require target-server compatibility to be confirmed before installation. If Composer or required capabilities are unavailable, document the actual blocker and choose a safe solution before changing architecture. Do not deploy over a live root.

## Current scope
Authentication shell, user and audit migrations, database-backed sessions/cache/jobs schema, first-admin CLI command, admin dashboard shell, basic feature tests.

## Deliberately excluded
No payments, public registration, public installer, experience management, media uploads, QR/NFC, Demo Engine, Site Builder, or claim of production readiness.
