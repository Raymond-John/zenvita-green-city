-- Phase 1B: unique binary placement seat (parent_id, binary_side)
-- LOCAL / STAGING ONLY until ops verifies production duplicates are zero.
-- Do NOT run against Hostinger/production without a prior duplicate audit.
--
-- Pre-check (must return 0 rows):
--   SELECT parent_id, binary_side, COUNT(*) c
--   FROM users
--   WHERE parent_id IS NOT NULL AND binary_side IN ('left','right')
--   GROUP BY parent_id, binary_side
--   HAVING c > 1;
--
-- MySQL UNIQUE allows multiple NULL parent_id rows (roots / non-tree users).
-- binary_side ENUM('left','right') NULL is compatible.
--
-- Application registration REQUIRES this index and will NOT create it at request time.
-- Local-only helper (loopback + allow-list + flag): scripts/phase1b_apply_local_uq.php

ALTER TABLE users
  ADD UNIQUE KEY uq_users_parent_side (parent_id, binary_side);
