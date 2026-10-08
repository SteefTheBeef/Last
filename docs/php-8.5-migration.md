# PHP 8.5 migration baseline

## Recorded source baseline

- Repository: https://github.com/SteefTheBeef/Last
- Upstream: https://github.com/Slig06/Fast3
- Migration branch: `migration/php-8.5`
- Baseline commit: `48bd4cfce22a17ae949b89bb5f67f52c15a5a871`
- Baseline tag: `baseline/php-5.5-source`
- FAST version declared by the source: `3.2.4f`.
- 88 PHP files were found during the initial inventory.
- The working tree was clean before baseline preparation.
- PHP and Composer were not available on the terminal PATH during the initial review.

The tag identifies the source before compatibility changes. Its name describes the
migration's starting point; it does not certify that this checkout has been tested
on PHP 5.5. No controller or dedicated server has been started for this step.

Local source and Git-history backups are stored in `.migration-backups/` and must
not be committed. Copy them to independent storage before relying on them for
recovery. These are source backups, not backups of a production deployment.

## Deployment inventory

This checkout has no `custom/` or `fastlog/` directory. `fast.php` currently has
empty enabled/disabled plugin lists and commented database credential examples.
That does not establish which plugins or credentials a deployed server uses.
`ingame.cfg` is a tracked example, not a production configuration.

Complete the following inventory privately for each deployed controller. Do not
put passwords, accounts, raw chat logs, player data, or database dumps in Git.

- [ ] Record OS, PHP executable/version, extensions, timezone, and startup command.
- [ ] Record dedicated-server version, configuration path, and configured ports.
- [ ] Record the deployed FAST revision and differences from this baseline.
- [ ] Record enabled/disabled plugins, custom plugins, and locale overrides.
- [ ] Record configured local databases and remote integrations, including Dedimania.
- [ ] Record restore flags, game modes, points rules, and match configuration.
- [ ] Identify configured output/copy locations outside the controller directory.

## Backup checklist

Take a consistent backup using a maintenance window or a suitable snapshot.
Keep the original deployment runnable with its original PHP runtime.

- [ ] Back up deployed source, `fast.php`, launch scripts, and dedicated configuration.
- [ ] Back up `custom/`, including custom plugins, database settings, and locales.
- [ ] Back up root `admin.<game>.<login>.xml.txt` admin lists.
- [ ] Back up root `store.<game>.<login>.fast` serialized controller state.
- [ ] Back up `votes.xml.txt` if present.
- [ ] Back up `fastlog/`, `matchlog/`, and any configured external output locations.
- [ ] Inventory and back up dedicated-server maps, match settings, records, and replays.
- [ ] If local MySQL is enabled, export schema and data consistently and test restoring
      the export into an isolated database.
- [ ] Record checksums and verify backup readability or restoration.
- [ ] Store backups outside the working checkout with appropriate access controls.

The paths above are derived from current source defaults. Check deployed
configuration and custom plugins for additional persistence paths.

## Isolated staging setup

- [ ] Use a separate controller directory and dedicated-server instance.
- [ ] Use a separate server account, configuration, and non-conflicting ports.
- [ ] Use a separate database; never point staging at the production database.
- [ ] Remove or redirect FTP/file-copy targets and other production write paths.
- [ ] Explicitly disable the `autoupdate` plugin in the staging configuration so
      upstream archives cannot overwrite migration code.
- [ ] Restrict staging access and use private test admin credentials.
- [ ] Do not copy production serialized state into an unrelated server instance.
- [ ] Keep test data and credentials out of Git; sanitize any committed fixtures.
- [ ] Reproduce existing behavior on the legacy runtime if safely available.
- [ ] Set up PHP 8.5 CLI and required extensions as the next migration step.

Do not use the legacy sample launchers unchanged: they explicitly target PHP 5
and include automatic-update restart behavior. Launcher changes belong to the
compatibility migration after the baseline has been captured.

## Behavioral baseline matrix

Record legacy observations and expected results first, then compare PHP 8.5
results using the same scenarios. Mark unavailable features explicitly rather
than treating untested behavior as passing.

| Area | Scenarios | Legacy result | PHP 8.5 result |
| --- | --- | --- | --- |
| Startup | Authentication, configuration, plugin loading, initial synchronization | Pending | Pending |
| Players | Join, leave, reconnect, spectator changes, team changes | Pending | Pending |
| Administration | Admin loading, permissions, commands, admin persistence | Pending | Pending |
| HUD and menus | Score panels, menus, records, map information, voting | Pending | Pending |
| Standard modes | Rounds, time attack, team, laps, stunts, cup | Pending | Pending |
| Custom modes | TeamRelay, TeamLaps, TeamRounds; mode transitions | Pending | Pending |
| Race lifecycle | Warmup, checkpoints, finish, round end, podium, map change | Pending | Pending |
| Persistence | Votes, records, match results, restart and state restoration | Pending | Pending |
| Integrations | Dedimania and local MySQL, enabled and unavailable | Pending | Pending |
| Recovery | Dedicated disconnect/reconnect, database outage, malformed responses | Pending | Pending |

Capture sanitized logs and representative XML-RPC inputs/outputs where useful.
Never execute untrusted serialized state or run a PHP 5.5 environment exposed to
the public network solely for testing.

## Rollback

The source baseline is recoverable from `baseline/php-5.5-source` or the local
source archive. The Git bundle preserves the history present at baseline creation.
Use a separate checkout/directory for recovery; do not reset over uncommitted work.
Restore deployment configuration, database, and controller state from the matching
backup and run the original runtime. Do not assume state written by migrated code
can be read by the old deployment until that compatibility has been verified.

## Step 1 completion gate

Repository baseline preparation is complete when the baseline tag, source archive,
and Git bundle have been created and validated. Operational baseline preparation
remains pending until the deployment inventory, backups, staging isolation, and
legacy behavior observations above have been completed.

No PHP 8.5 compatibility claim should be made from source inspection alone.

## Local PHP 8.5 validation tooling

The Windows x64 development runtime is pinned to PHP 8.5.11 NTS. Run
`./tools/Install-Php.ps1` from PowerShell to install it in `.tools/php/`.
The installer downloads the official PHP Windows archive and verifies its pinned
SHA-256 checksum. It does not modify system PATH or an existing PHP installation.
The Microsoft Visual C++ 2015-2022 x64 runtime must already be available.

Run `./tools/Test-Php.ps1` to perform runtime smoke tests and syntax-check all
project PHP files. Reports are written to `.tools/reports/php-validation.txt`.
The script exits unsuccessfully if the runtime checks or any syntax check fail.
It does not execute `fast.php` or connect to servers or databases.

`tools/php.ini` enables E_ALL diagnostics and the mbstring, mysqli, OpenSSL, and
ZIP extensions. XML and zlib are included in the installed Windows runtime and
are checked explicitly. mysqli is preparation for database migration, not a
replacement for the existing mysql_* calls. Composer is not required for these
checks and has not been installed.

For a separately installed PHP 8.5 runtime, supply its executable using
`./tools/Test-Php.ps1 -PhpPath /path/to/php` and make sure the same extensions are
available. The local installer itself supports Windows x64 only.

### First executable baseline

- PHP 8.5.11 started successfully with the development configuration.
- All 14 runtime smoke checks passed: version, CLI, diagnostics, six extensions,
  XML parsing, compression, UTF-8 substring handling, and array serialization.
- Syntax checks covered 88 existing PHP files plus the new runtime test.
- 88 files passed syntax checking; `includes/GbxRemote.fast.php` failed with an
  unexpected `{` at line 889.
- The same file reported deprecated `(double)` and `(boolean)` casts at lines
  255 and 279.

These are recorded migration findings, not tooling failures. Application code
has not been changed to address them yet. Syntax success elsewhere does not
prove runtime compatibility; constructors, removed APIs, and event behavior
still need audit and regression tests.

### Shared GBX/XML-RPC compatibility batch

Updated `includes/GbxRemote.fast.php` and `includes/GbxRemote.response.php`:

- Replaced legacy constructors with `__construct`, including inherited client initialization.
- Replaced removed curly-brace string offsets and deprecated casts.
- Registered XML callbacks as object callables instead of using `xml_set_object`.
- Released parser objects without the deprecated `xml_parser_free` function.
- Initialized parser buffers and parameter lists and used the declared tag property.
- Made string escaping explicit to preserve legacy quote handling under PHP 8 defaults.
- Corrected the socket property used when setting the initialization timeout.
- Finalized XML parsing so incomplete XML is rejected.

`tools/tests/xmlrpc.php` provides 39 offline checks for value encodings, exact
request bytes, nested responses, binary/date values, faults, malformed XML,
callbacks, constructor initialization, queue draining, and header decoding.
PHP diagnostics are converted to exceptions in this suite. `tools/Test-Php.ps1`
now runs it after successful syntax checks.

Validation under PHP 8.5.11: all 90 PHP files passed syntax checks, all 14 runtime
checks passed, and all 39 protocol checks passed. No dedicated server, network
transport, or production database was exercised. Timestamp-based date conversion
and transport recovery behavior still require focused tests before further fixes.

### HTTP and remote database compatibility batch

Updated `includes/web_access.php` and `includes/xmlrpc_db_access.php`:

- Modernized constructors and declared the HTTP query-time field.
- Initialized HTTP response containers as arrays for error callback handling.
- Removed undefined error variables from send/receive failure messages.
- Preserved positional callback context when PHP 8 would interpret string keys
  as named arguments.
- Fixed request clearing to return the instance queues rather than undefined variables.
- Guarded empty multicall responses and rejected synchronous transport requests.

`tools/tests/webaccess.php` provides 30 strict offline checks covering HTTP
construction, request headers, callback errors, retry backoff, fixed-length and
chunked responses, gzip/deflate, incomplete responses, cookies, keepalive metadata,
and database authentication/multicall/callback/reset behavior. A fake transport
and fixture parsing are used; no network connection is opened.

Validation under PHP 8.5.11: 91 PHP files pass syntax checks, with 14 runtime,
39 protocol, and 30 HTTP/database checks passing. Real HTTP socket lifecycle,
service interoperability, and database integration still require staging tests.

### Remaining audit findings

| Module | Confirmed work to investigate next |
| --- | --- |
| `includes/web_access.php` | Real socket lifecycle, timeout handling, and malformed HTTP metadata |
| `includes/xmlrpc_db_access.php` | Service interoperability and malformed response handling |
| `includes/replayparser.inc.php` | Legacy constructor, `utf8_encode`, XML callback registration and parser lifecycle |
| `includes/xml_parser.php` | Deprecated parser cleanup and malformed input handling |
| `includes/fast_general.php` | Legacy procedural ZIP APIs |
| `plugins/plugin.02.mysql.php`, `plugins/plugin.85.match.php` | Removed `mysql_*` APIs and reconnect/result semantics |

This inventory is not a complete runtime audit. Plugin dispatch, configuration,
state restoration, custom plugins, and game-mode behavior remain pending.
