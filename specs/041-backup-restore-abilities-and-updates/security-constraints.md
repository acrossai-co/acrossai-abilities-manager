# Security Constraints: Feature 041

**Feature**: [spec.md](./spec.md)
**Plan**: [plan.md](./plan.md)
**Backfilled**: 2026-07-18 (constraints as enforced by shipped 0.0.9 + 0.0.10)

## Threat Model Summary

The Feature 041 surface exposes filesystem writes and archive extraction through the Abilities API. The primary threats are: (1) zip-slip via crafted archives escaping the target directory, (2) path traversal via untrusted `target` inputs escaping ABSPATH, (3) execution of PHP inside the backups directory if any zip is misinterpreted as an entry point, (4) directory-listing enumeration of backup filenames, (5) DoS via unbounded archive sizes, (6) file modification during a `DISALLOW_FILE_MODS` lockdown.

## Constraints (all enforced by shipped code)

### C-041-SEC-01 — Zip-slip audit before extraction

`Zip_Extract::audit_zip_entries()` iterates `ZipArchive::statIndex()` and rejects any entry whose name:

- contains a `..` segment (per-segment check on `explode('/', $name)`),
- starts with `/` (absolute path),
- contains a `\` (backslash),
- contains a `\0` (null byte),
- has an empty name string.

Rejection happens BEFORE the archive is handed to `unzip_file()` / `ZipArchive::extractTo` — no partial extraction on failure.

### C-041-SEC-02 — ABSPATH boundary check on custom paths

`Zip_Target_Resolver::resolve_abspath_relative()` uses `realpath()` on the resolved parent directory and asserts the result equals `$base` or begins with `$base . '/'` (mirroring the pattern established by `FileManager/File_Read.php:90-99`). Any path that resolves outside ABSPATH is rejected with `path_out_of_bounds`.

### C-041-SEC-03 — Backups directory hardening

`Backups_Storage::resolve_dir()` writes on first use:

- An `.htaccess` that denies GET/POST for `.php`, `.phtml`, `.phar`, `.pl`, `.py`, `.jsp`, `.asp`, `.htm`, `.html`, `.shtml` extensions AND adds `Options -Indexes` to disable directory listing. Notably, the `.htaccess` MUST NOT globally deny all requests (early implementation attempt was corrected in the same PR) — otherwise `zip-create`'s returned `file_url` becomes unreachable.
- An empty `index.php` so servers without `.htaccess` (nginx) still get a defense against directory enumeration.

### C-041-SEC-04 — Random filenames

`Backups_Storage::random_backup_filename()` appended a 12-character `[A-Za-z0-9]` random suffix on 0.0.9. **Superseded by Feature 042** which switches to `{slug}-{unix}-{ms}.zip` for readability, trading enumeration-by-guessing defense for time-sortability. Directory listing remains disabled.

### C-041-SEC-05 — File_Mods_Guard on all mutating abilities

Every mutating ability short-circuits via `File_Mods_Guard::blocked_response('install')`:

- `Zip_Upload::execute()`
- `Zip_Extract::execute()`
- `Zip_Delete::execute()`
- `Plugin_Update::execute()`
- `Theme_Update::execute()`

Read-only Zip_Download and Zip_List do not guard (they don't write).

### C-041-SEC-06 — Capability gates

- `Zip_Create`, `Zip_Upload`, `Zip_Extract`, `Zip_Download`, `Zip_List`, `Zip_Delete`: `current_user_can('manage_options')` per Constitution §IV.
- `Plugin_Update`: `current_user_can('manage_options') && current_user_can('update_plugins')` — matches WP core's own admin gate for plugin updates.
- `Theme_Update`: `current_user_can('manage_options') && current_user_can('update_themes')` — matches WP core's own admin gate for theme updates.

Per the standing user directive `feedback_skip_permission_callback_audit` in memory, a full REST-layer permission_callback audit is out of scope; each ability's `permission_callback` is inspected by the source-scan test suite.

### C-041-SEC-07 — Size caps

- Archive size cap (default 512 MB, filter `acrossai_abilities_manager_zip_max_bytes`) enforced at THREE points:
  1. `Zip_Create::estimate_tree_size()` before writing the zip
  2. `Zip_Create::execute()` on the finalized zip size
  3. `Zip_Extract::audit_zip_entries()` on the sum of `statIndex()->size` values (uncompressed size)
- Chunked upload cap (`Zip_Upload`): 8 MB per chunk / 64 MB per session (both filterable), enforced on the base64-encoded payload before decoding.

### C-041-SEC-08 — Zip magic-byte validation on upload

`Zip_Upload::validate_zip_magic()` reads the first 4 bytes on finalize and rejects anything that isn't `PK\x03\x04` (standard local file header), `PK\x05\x06` (empty archive), or `PK\x07\x08` (spanned archive marker). Non-zip payloads are rejected with a clean error.

### C-041-SEC-09 — Managed-path boundary on Zip_Download / Zip_List / Zip_Delete

All three abilities resolve their `file_path` input via `Backups_Storage::resolve_managed_path()`, which requires the resolved parent to sit inside `acrossai-backups/` or `acrossai-staging/`. Any path outside those two dirs is rejected with `path_out_of_bounds`.

### C-041-SEC-10 — No shell execution

The feature uses no `shell_exec`, `exec`, `system`, `passthru`, `proc_open`, or backtick operators. All archive work is pure PHP via `ZipArchive` + WP core `unzip_file()` / `download_url()` / upgraders.

## Fix constraint (0.0.10)

### C-041-SEC-11 — Per-segment hidden-path check

`Zip_Create`'s `include_hidden=false` path checks EVERY segment of the entry's forward-slashed relative path via `has_hidden_segment()` — not just the current entry basename. This closes the 0.0.9 information-leak where a caller asking to exclude hidden files still shipped `.git/objects/xxx` (and every other file inside a hidden directory) because `RecursiveIteratorIterator::SELF_FIRST` does not stop descent when `continue`-d on the outer loop.
