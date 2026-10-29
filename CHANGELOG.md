# Changelog

## [Unreleased]

### Fixed

- An `UnreachableException` for a mailer that failed no longer carries its credentials: its
  previous is a copy of the mailer's exception (the same class and code, its message through
  omnisense/omnibill's `Http\Redaction::text()`), and no trace of the chain keeps its arguments,
  where an SMTP transport's `AUTH PLAIN` command stood. Needs omnisense/omnibill's `Http\Redaction`.
