# Sail stubs

These are the canonical copies of the Turso-authored files used by the
[Laravel Sail guide](../../docs/user-manual/laravel-sail.md). Copy them into the
published runtime directory under `docker/` after running
`sail artisan sail:publish`.

| File | What it is |
|---|---|
| `run_turso` | Boot preflight. Verifies that the libSQL extension is loaded and fails the container when it is missing. |
| `sail-entrypoint` | Runs `run_turso`, then forwards all arguments to upstream's `start-container`. |
| `Dockerfile.fragment` | The complete Dockerfile to paste over the published one. Pins the CLI through Composer and installs the extension into the image. |
| `compose.fragment.yaml` | The complete `compose.yaml` service block to paste into the app's existing compose file. |

`start-container`, `supervisord.conf` and `php.ini` are **upstream-owned**.
They are created by `sail:publish` from the selected runtime directory and must
not be copied into this repository.

`PHP_VERSION` in `compose.fragment.yaml` is the single version switch. It is
passed as a build argument and drives the Docker context image tag, the apt
packages, the installer flags and the `php.ini` destination.

## Local verification

```bash
bash -n stubs/sail/run_turso stubs/sail/sail-entrypoint
python3 scripts/check_docs.py
docker compose -f stubs/sail/compose.fragment.yaml config -q
```

`python3 scripts/check_docs.py` also rejects unresolved placeholders and
hardcoded PHP 8.3 paths in both the stubs and the guide.
