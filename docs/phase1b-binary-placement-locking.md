# Phase 1B: Binary placement locking strategy

## Problem
Two concurrent registrations can both read the same empty `(parent_id, binary_side)` seat and both insert, creating a duplicate placement.

A plain database transaction does **not** prevent this. Without row locks, both transactions can `SELECT` the empty seat before either `INSERT` commits.

## Strategy (implemented in `open_seat()`)

1. Placement always runs inside the registration transaction (`beginTransaction` … `commit` / `rollBack`).
2. For each candidate parent, `open_seat()` runs `SELECT id FROM users WHERE id = ? FOR UPDATE`.
3. While that parent row lock is held, it re-checks whether the requested side already has a child.
4. If empty, it returns that seat. The parent lock is held until the registration transaction commits (or rolls back), so a competing registration blocks on the same parent and cannot claim the seat as empty.
5. If occupied, it walks to the child (spillover) and repeats — preserving the existing left/right spillover algorithm.
6. On `INSERT`, unique key `uq_users_parent_side (parent_id, binary_side)` rejects any residual race (MySQL error 1062). Multiple `NULL` parent rows remain allowed under MySQL UNIQUE NULL semantics.
7. Deadlocks (1213 / SQLSTATE 40001) and duplicate-key failures roll back the whole registration transaction so no partial member is left behind.

## Why this stops two claims on one empty seat
Transaction A locks parent P, sees left empty, inserts child, commits.  
Transaction B, waiting on `FOR UPDATE` of P, then runs, sees left occupied, and spills deeper (or gets 1062 if it somehow targeted the same seat without re-check).  

The unique index is the final safety net; the lock is the primary concurrency control.
