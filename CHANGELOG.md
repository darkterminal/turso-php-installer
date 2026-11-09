## [2.2.0] - 2026-11-09

- feat(installation): PHP 8.5 in the version picker + fact-based asset-miss error [Imam Ali Mustofa]
- feat(installation): verify the result right after install/update [Imam Ali Mustofa]
- fix(installer): detect installation via metadata, clean record on uninstall [Imam Ali Mustofa]
- fix(installation): interactive answers must actually be applied [Imam Ali Mustofa]
- fix(server): make server:check an honest requirement gate [Imam Ali Mustofa]
- fix(server): guard ca-cert-show --raw against an empty store [Imam Ali Mustofa]
- fix(sqld): reject mutually exclusive gRPC variables [Imam Ali Mustofa]
- fix(sqld): verify daemon liveness before reporting a started daemon [Imam Ali Mustofa]
- fix(sqld): friendly failure when an environment references a deleted token [Imam Ali Mustofa]
- fix(sqld): guard env-edit form hints and fix SQLD_NO_WELCOME ternary [Imam Ali Mustofa]
- fix(env): env-edit survives custom variables; env-new validates --variables [Imam Ali Mustofa]
- fix(sqld): jwt key into install dir, ensured data dirs, self-healing daemon lists [Imam Ali Mustofa]
- fix(sqld): clean guard failures instead of TypeErrors; ini-resolver hardening [Imam Ali Mustofa]
- refactor(token): lazy keypair generation, unique and self-cleaning key files [Imam Ali Mustofa]
- feat(sqld): python3-first interpreter for certificate scripts [Imam Ali Mustofa]
- fix(config): run the built PHAR in production so dev commands disappear [Imam Ali Mustofa]
- fix(console): fail loudly with non-zero exit codes on error paths [Imam Ali Mustofa]
- fix(build): make the dev binary executable [Imam Ali Mustofa]
- chore: drop stale VERSION constant from helpers [Imam Ali Mustofa]
- test: add a minimal smoke suite so the test command runs green [Imam Ali Mustofa]
- ci: dependency updater 3x weekly behind workflow_dispatch; push trigger dropped [Imam Ali Mustofa]
- chore: refresh symfony 8.1 transitive components (clock/string/translation) [Imam Ali Mustofa]
- docs(README): aligned support claims, working examples, production pointer [Imam Ali Mustofa]
- deps: automated Composer maintenance updates throughout the cycle (86 bot commits folded) [github-actions[bot]]

## [2.1.0] - 2025-03-03

- release: minor version 2.1.0 [Imam Ali Mustofa]
- fix: support FrankenPHP php.ini creation cz FrankenPHP docker image is not contain default php.ini file [Imam Ali Mustofa]
- refactor: change Illuminate\Http to Guzzle\Http and make it stable [Imam Ali Mustofa]
- feat: remove PHP8.0 and add PHP8.4 support [Imam Ali Mustofa]
- chore: update CHANGELOG.md for patch version 2.0.16 [Imam Ali Mustofa]
- release: patch version 2.0.16 [Imam Ali Mustofa]
- fix: remove illuminate/http and change to guzzlehttp/guzzle [Imam Ali Mustofa]
- Automated Composer update [github-actions[bot]]

## [2.0.16] - 2025-02-27

- release: patch version 2.0.16 [Imam Ali Mustofa]
- fix: remove illuminate/http and change to guzzlehttp/guzzle [Imam Ali Mustofa]
- chore: update CHANGELOG.md for patch version 2.0.15 [Imam Ali Mustofa]

## [2.0.15] - 2025-02-27

- release: patch version 2.0.15 [Imam Ali Mustofa]
- deps: update all dependencies [Imam Ali Mustofa]
- chore: update CHANGELOG.md for patch version 2.0.14 [Imam Ali Mustofa]
- release: patch version 2.0.14 [Imam Ali Mustofa]
- Automated Composer update [github-actions[bot]]
- Automated Composer update [github-actions[bot]]
- Automated Composer update [github-actions[bot]]
- Automated Composer update [github-actions[bot]]
- Automated Composer update [github-actions[bot]]
- Automated Composer update [github-actions[bot]]

## [2.0.14] - 2025-02-27

- release: patch version 2.0.14 [Imam Ali Mustofa]
- chore: update CHANGELOG.md for patch version 2.0.13 [Imam Ali Mustofa]

## [2.0.13] - 2025-01-17

- release: patch version 2.0.13 [Imam Ali Mustofa]
- feat(sqld): add daemon list and kill command [Imam Ali Mustofa]
- feat(fix): adding more validations, and fix grpc address [Imam Ali Mustofa]
- refactor: change application namespace [Imam Ali Mustofa]
- Automated Composer update [github-actions[bot]]
- fix: allow write permission for this job dependency_updater.yml [Imam Ali Mustofa]
- build(ci): fix git commit action [Imam Ali Mustofa]
- chore: update CHANGELOG.md for patch version 2.0.12 [Imam Ali Mustofa]

## [2.0.12] - 2025-01-16

- release: patch version 2.0.12 [Imam Ali Mustofa]
- build(deps): update all dependencies into the latest versions [Imam Ali Mustofa]
- chore: update CHANGELOG.md for patch version 2.0.11 [Imam Ali Mustofa]

## [2.0.11] - 2025-01-14

- release: patch version 2.0.11 [Imam Ali Mustofa]
- feat(repository): change how to handle read release metadata [Imam Ali Mustofa]
- chore: update CHANGELOG.md for patch version 2.0.10 [Imam Ali Mustofa]

## [2.0.10] - 2025-01-14

- release: patch version 2.0.10 [Imam Ali Mustofa]
- feat(repository): change url repository to download the extension [Imam Ali Mustofa]
- feat: token validation for database [Imam Ali Mustofa]
- chore: update CHANGELOG.md for patch version 2.0.9 [Imam Ali Mustofa]

## [2.0.9] - 2025-01-10

- release: patch version 2.0.9 [Imam Ali Mustofa]
- fix(sqld:env-new): failed to create environment [Imam Ali Mustofa]
- chore: create changelog histories [Imam Ali Mustofa]
- fix(vesion): using global config version [Imam Ali Mustofa]
