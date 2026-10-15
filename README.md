# podman-installer

A one-time installer for rootless Podman apps: run it, set up, remove it.

You start the installer container once. It walks you through installing an app as rootless Podman Quadlets in your browser, and then it's gone. From then on, systemd runs the app and [`lpod`](https://github.com/foxws/lpod) manages it. The installer doesn't keep running as a platform (like Coolify or Portainer), and it doesn't replace Cockpit.

> [!WARNING]
> Early work in progress. Only the wizard, the manifest, the requirements and command steps, and the one-time link exist so far.

## How it works

- **Host checks** come from what the installer sees through the Podman socket: the Podman version, cgroup v2 and subordinate IDs. What it can't see from inside its container, such as linger, it lists with how to check and fix it on the host. When `lpod doctor --json` ran on the host first and its report is mounted at `/run/installer/doctor.json` (`INSTALLER_DOCTOR_REPORT`), the installer uses that full report instead. Either way, it stops at the requirements step while a check fails.
- **Access** needs the one-time link that `php artisan installer:link` prints, like `http://127.0.0.1:8085/?token=…`. The link works once: the first browser that opens it gets in, and the token is removed from the address. Without it, every page, including Livewire's updates, returns 403. The installer listens on `127.0.0.1` by default; reach it on a server through `ssh -L 8085:127.0.0.1:8085`.
- **Steps** come from the app. Each app ships an `installer/` folder with a `manifest.yml` and a Markdown file per step, and builds its own installer image on top of this one:

  ```dockerfile
  FROM ghcr.io/foxws/podman-installer:1
  COPY installer/ /app/installer/
  ```

## The manifest

```yaml
name: Example

steps:
  - id: welcome
    file: welcome.md

  - id: requirements
    type: requirements
    file: requirements.md
```

| Key    | Description                                                                  |
| ------ | ---------------------------------------------------------------------------- |
| `id`   | A unique id for the step. It's also the title when the Markdown doesn't set one |
| `file` | The Markdown file next to the manifest. Its front matter can set a `title`   |
| `type` | `content` (default), `requirements`, which shows the `lpod doctor` checks, or `command` |

Raw HTML in the Markdown is stripped. See [`installer/`](installer) for the example.

### Command steps

A `command` step asks for its inputs, then runs a command in the app's container with `podman exec`, such as an Artisan command or a seeder:

```yaml
- id: admin
  type: command
  file: admin.md
  inputs:
    - { name: name, label: Name }
    - { name: email, label: Email, type: email, rules: required|email }
    - { name: password, label: Password, type: password, rules: required|min:12 }
  run:
    container: systemd-stry
    command: [php, artisan, users:create, --super-admin, --no-interaction, "--name={name}", "--email={email}"]
    stdin: "{password}"
    skip_if: [php, artisan, users:exists, --super-admin]

- id: seed
  type: command
  file: seed.md
  run:
    container: systemd-stry
    command: [php, artisan, db:seed, --class=InstallerSeeder, --force]
    env:
      ADMIN_PASSWORD: "{password}"
```

- `{name}` is replaced with the value of the input with that name.
- `command` and `skip_if` are lists of arguments and never go through a shell, so a value can't add a command of its own.
- Inputs of `type: password`, or with `secret: true`, can only be passed through `stdin` or `env`, because arguments show up in `ps` and `podman inspect`. `env` values are handed to `podman exec` by name only (`--env NAME`). Secret values are cleared once the command ran, and hidden in the output the installer shows.
- When `skip_if` succeeds, the step was already done and its command is skipped, so running the installer again is safe.
- `rules` are Laravel validation rules, `required` by default. `type` is `text` (default), `email` or `password`.
- The installer only continues once the command succeeded.

## The image

`ghcr.io/foxws/podman-installer` runs the installer with FrankenPHP on port 8085. The `edge` tag follows `main`, and releases get `1`, `1.2` and `1.2.3` tags. The image keeps no data and needs no `.env`.

Until there's an lpod command to start it, run it by hand:

```bash
podman run --rm -p 127.0.0.1:8085:8085 \
    -v "$XDG_RUNTIME_DIR/podman/podman.sock:/run/podman/podman.sock" \
    --security-opt label=disable \
    ghcr.io/foxws/podman-installer:edge
```

It prints the one-time link when it starts. For the full host check, add `-v /tmp/doctor.json:/run/installer/doctor.json:ro` after `lpod doctor --json > /tmp/doctor.json`. Inside the container it runs as root, which rootless Podman maps to your own user, the owner of the socket. `label=disable` lets it use the socket on SELinux hosts. Run `systemctl --user enable --now podman.socket` once if the socket doesn't exist.

Every image is built with provenance. Check where it came from with:

```bash
gh attestation verify oci://ghcr.io/foxws/podman-installer:edge --owner foxws
```

## Development

```bash
composer install
npm install && npm run build
php artisan serve --host=127.0.0.1 --port=8085
php artisan installer:link
```

Run the tests with `vendor/bin/pest`.

## License

MIT. See [LICENSE](LICENSE).
