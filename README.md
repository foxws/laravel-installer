# Laravel Installer

An install wizard for Laravel apps, in a container: run it, set up, remove it.

You start the installer container once. It walks you through installing a Laravel app in your browser, and then it's gone. The installer doesn't keep running as a platform (like Coolify or Portainer), and it doesn't replace Cockpit.

It supports rootless Podman with Quadlets today: from then on, systemd runs the app and [`lpod`](https://github.com/foxws/lpod) manages it. Docker and Docker Compose are planned.

> [!WARNING]
> Early work in progress.

## Two ways to use it

Since the installer runs in a container, you choose what's in that container:

- **The provided image** asks for the app's git repository and the branch, tag or commit to install. It clones that version into the install location you mounted, checks whether the app's installer can work here, and then walks through its steps. One image installs any app that ships an `installer/` folder. See [Installing from a repository](#installing-from-a-repository).
- **Your own image**, built on top of it, bakes the app's `installer/` folder in, and whatever else your steps need, such as extra tools or packages. It starts with the steps right away:

  ```dockerfile
  FROM ghcr.io/foxws/laravel-installer:2
  COPY installer/ /app/installer/
  ```

## How it works

- **Host checks** run through the Podman socket: the Podman version (5.6 or newer, for Quadlet), cgroup v2 and subordinate IDs. What the installer can't see from inside its container, such as linger and the proxy's ports, it lists with how to check and fix it on the host. It stops at the requirements step while a check fails.
- **Access** needs the one-time link that `php artisan installer:link` prints, like `http://127.0.0.1:8085/?token=…`. The link works once: the first browser that opens it gets in, and the token is removed from the address. Without it, every page, including Livewire's updates, returns 403. The installer listens on `127.0.0.1` by default; reach it on a server through `ssh -L 8085:127.0.0.1:8085`, or open it to your network over HTTPS (see below).
- **Steps** come from the app: an `installer/` folder with a `manifest.yml` and a Markdown file per step, either in the app's repository or baked into its own image.

## The manifest

```yaml
name: Example
description: A short line about the app
version: '2.1'
installer: '^2.0'

links:
  - { label: Help, url: 'https://example.com/docs' }
  - { label: Source, url: 'https://github.com/example/app' }

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
| `type` | `content` (default), `requirements`, which shows the host checks, `secrets`, `services` or `command` |

The optional `description`, `version` and `links` show in the header: what the app is, the version the installer installs, and links to the app's help, source and such, which open in a new tab. Without a `version`, an app installed from a tag shows the tag. Quote the version, so YAML doesn't read `2.10` as the number `2.1`. Links must start with `http://` or `https://`.

The optional `installer` is the range of installer versions the app's installer works with, such as `'^2.0'`. When the installer is installed from a repository, it checks the range before it starts.

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
    container: systemd-{application}
    command: [php, artisan, users:create, --super-admin, --no-interaction, "--name={name}", "--email={email}"]
    stdin: "{password}"
    skip_if: [php, artisan, users:exists, --super-admin]

- id: seed
  type: command
  file: seed.md
  run:
    container: systemd-{application}
    command: [php, artisan, db:seed, --class=InstallerSeeder, --force]
    env:
      ADMIN_PASSWORD: "{password}"
```

- `{name}` is replaced with the value of the input with that name. `{application}` is the app's quadlet prefix from the workspace (`PODMAN_QUADLET_PREFIX`, or `APP_NAME` in kebab case), so `container: systemd-{application}` follows the app's name, such as `systemd-my-project-dev` for `APP_NAME=my-project-dev`.
- `command` and `skip_if` are lists of arguments and never go through a shell, so a value can't add a command of its own.
- Inputs of `type: password`, or with `secret: true`, can only be passed through `stdin` or `env`, because arguments show up in `ps` and `podman inspect`. `env` values are handed to `podman exec` by name only (`--env NAME`). Secret values are cleared once the command ran, and hidden in the output the installer shows.
- When `skip_if` succeeds, the step was already done and its command is skipped, so running the installer again is safe.
- `rules` are Laravel validation rules, `required` by default. `type` is `text` (default), `email` or `password`.
- The installer only continues once the command succeeded.

### Requirements steps

A `requirements` step checks the host through the Podman socket: the Podman version, cgroups v2 and subordinate IDs. Linger and the ports show as unknown, with how to check them on the host. The installer only continues once none of the checks fail.

With a workspace mounted, it also checks whether the app is installed already, by looking for its `{application}.container` unit. The installer is meant for a first install: on a running app it would reinstall and restart the services. When an earlier run stopped halfway, start the installer with `-e INSTALLER_ALLOW_EXISTING=true` to continue anyway. Once the installer installed the services itself, going back to the requirements doesn't stop it.

To test an installer, such as its steps' content and styling, start it with `-e INSTALLER_ALLOW_SKIPPING=true`. Each step then has a "Skip step" button while it doesn't let the installer continue, such as after failed requirements or a failed command. Don't use this for a real install: the later steps expect the earlier ones to be done.

### Secrets steps

The containers can't start before the Podman secrets they use exist, so put a `secrets` step before the `services` step. It renders the same presets, lists every `Secret=` of their units, and creates the missing ones with `podman secret create` through the socket, without lpod:

```yaml
- id: secrets
  type: secrets
  file: secrets.md
  presets: [production]
  values:                          # type=env secrets, by target: the .env key to read
    POSTGRES_DB: DB_DATABASE
    POSTGRES_USER: DB_USERNAME
    POSTGRES_PASSWORD: DB_PASSWORD
  files:                           # mounted secrets, by target: the workspace file
    /app/.env: .env
```

- Mounted secrets, such as the app's `.env` at `/app/.env`, are created from a file in the workspace, the app's `.env` by default. Podman reads the file itself.
- `type=env` secrets take their value from the key in `values`. Without one, or when the `.env` doesn't have it, you type the value in, or leave it empty to generate one.
- Values only go to Podman through stdin and are cleared right after. Existing secrets are kept, unless you choose to replace them.

### Services steps

A `services` step renders the app's presets with [foxws/laravel-podman](https://github.com/foxws/laravel-podman), installs them as Quadlets and starts them:

```yaml
- id: services
  type: services
  file: services.md
  presets: [production, proxy]
```

- The presets are rendered into the app's folder on the host, the workspace (see [Installing services](#installing-services)). Its `containers/stubs` and `config/podman.php` apply, so published and custom presets work as they do with `php artisan podman:setup`.
- The app's `.env` decides what's rendered, but only its `APP_NAME`, `APP_URL`, `APP_ENV` and `PODMAN_*` values are read. Secrets such as `APP_KEY` never reach the installer.
- Without `presets`, the app's `PODMAN_DEFAULT_PRESETS` are installed. List them in the step, since the package's own defaults are meant for development.
- Each unit of a `.quadlets` file is written to `~/.config/containers/systemd`, as `podman quadlet install` does. Sockets and timers go to `~/.config/systemd/user` and are enabled. Then systemd reloads, and the installer shows the state of what it started until it all runs:
  - With an on-demand socket, it wakes the app the way a visitor would: a short-lived container on the host's network, from the installer's own image, requests `/up` on the socket's address. The socket then starts its proxy, which requires the app, which wants the other services, so they keep running until the proxy is idle. Started directly, the proxy would get no socket, and services with `StopWhenUnneeded=yes` would stop again right away. Set `INSTALLER_IMAGE` when the installer can't look up its own image.
  - Otherwise it starts the containers. Containers a timer starts, such as the scheduler, are left to their timer.
- Running it again renders and installs the services again, so it's safe to retry after fixing a failed service.

## Installing from a repository

Without an `installer/` folder of its own, the installer starts by asking for the app:

1. **Repository**: an `https://` git address, such as `https://github.com/example/app.git`. For a private repository, enter an access token, or start the installer with `INSTALLER_GIT_TOKEN`.
2. **Version**: a tag (the newest is chosen first), a branch, or a full commit hash. It shows the commit it will install before it fetches anything.
3. **Check**: it fetches only that version into the workspace, and checks whether its installer can work here:
   - the manifest is valid,
   - the app supports this version of the installer (its `installer` range),
   - what the steps need is mounted: the Podman socket for requirements, command, secrets and services steps, and the Quadlet and systemd folders and the D-Bus session bus for services steps,
   - the app has a `.env` file, when it has secrets or services steps (a warning: a fresh clone doesn't have one yet).

The installer only starts the steps once nothing fails. Until a step changed something on the host, "Choose another version" goes back to pick another one.

```bash
podman run --rm --pull=newer -p 127.0.0.1:8085:8085 \
    --userns=keep-id \
    -v "$XDG_RUNTIME_DIR/podman/podman.sock:/run/podman/podman.sock" \
    -v "$HOME/my-project:/workspace" -e PODMAN_WORKING_PATH="$HOME/my-project" \
    --security-opt label=disable \
    ghcr.io/foxws/laravel-installer:2
```

The workspace must be empty, or a checkout of the same repository from an earlier run, so the installer never writes over other files. Add the mounts of [Installing services](#installing-services) when the app's installer has a services step.

| Variable                  | Description                                                              |
| ------------------------- | ------------------------------------------------------------------------ |
| `INSTALLER_SOURCE`        | The repository to suggest                                                |
| `INSTALLER_SOURCE_FOLDER` | The folder in the repository with the `manifest.yml`, `installer` by default |
| `INSTALLER_GIT_TOKEN`     | A token for a private repository, such as a GitHub fine-grained token with read access to its contents |

Git only fetches over HTTPS, without submodules and without asking for credentials. A token is sent as an HTTP header through git's environment: it doesn't show up in the process list, the remote's address or `.git/config`, and it's hidden in errors. Pass it as a Podman secret rather than on the command line, with `--secret my-token,type=env,target=INSTALLER_GIT_TOKEN`. The manifest and steps come from the network, so pin a tag or commit you trust: the installer shows the commit before it fetches, and in the header afterwards.

## The image

`ghcr.io/foxws/laravel-installer` runs the installer with FrankenPHP on port 8085. The `edge` tag follows `main`, and releases get `2`, `2.1` and `2.1.3` tags. The image keeps no data and needs no `.env`.

Version 2 no longer ships the example installer, so on its own it asks for the app's repository. Images built on top of it with their own `installer/` folder work as before.

Start it with:

```bash
podman run --rm --pull=newer -p 127.0.0.1:8085:8085 \
    -v "$XDG_RUNTIME_DIR/podman/podman.sock:/run/podman/podman.sock" \
    --security-opt label=disable \
    ghcr.io/foxws/laravel-installer:2
```

It prints the one-time link when it starts. Ctrl+C stops it, and `--rm` removes it.

### On your network, over HTTPS

To open it from another machine, publish it on the server's address and tell the installer that address with `INSTALLER_HOST`:

```bash
podman run --rm --pull=newer -p 192.168.1.100:8085:8085 \
    -e INSTALLER_HOST=192.168.1.100 \
    -v "$XDG_RUNTIME_DIR/podman/podman.sock:/run/podman/podman.sock" \
    --security-opt label=disable \
    ghcr.io/foxws/laravel-installer:2
```

With `INSTALLER_HOST`, the installer serves HTTPS and prints `https://192.168.1.100:8085/?token=…`. A hostname works too, such as `INSTALLER_HOST=server.lan`. The certificate comes from Caddy's internal CA, which your browser doesn't know, so it warns once; the connection is encrypted all the same. When you publish another port, such as `-p 192.168.1.100:9000:8085`, pass it as `INSTALLER_PUBLISHED_PORT=9000` so the link matches.

Without `INSTALLER_HOST` it serves plain HTTP, which is only meant for `127.0.0.1` and SSH tunnels. Without `--userns=keep-id`, it runs as root inside the container, which rootless Podman maps to your own user, the owner of the socket. `label=disable` lets it use the socket on SELinux hosts. Run `systemctl --user enable --now podman.socket` once if the socket doesn't exist.

### Installing services

`services` steps need the app's folder (the workspace), the user's Quadlet and systemd folders, and the user's D-Bus session bus, to reach systemd. `--userns=keep-id` runs the installer as your own UID, so systemd accepts it and the files it writes are yours:

```bash
podman run --rm --pull=newer -p 127.0.0.1:8085:8085 \
    --userns=keep-id \
    -v "$XDG_RUNTIME_DIR/podman/podman.sock:/run/podman/podman.sock" \
    -v "$HOME/my-project:/workspace" -e PODMAN_WORKING_PATH="$HOME/my-project" \
    -e PODMAN_QUADLET_UID="$(id -u)" -e PODMAN_QUADLET_GID="$(id -g)" \
    -v "$HOME/.config/containers/systemd:/run/installer/quadlets" \
    -v "$HOME/.config/systemd/user:/run/installer/units" \
    -v "$XDG_RUNTIME_DIR/bus:/run/installer/bus" \
    -e DBUS_SESSION_BUS_ADDRESS=unix:path=/run/installer/bus \
    --security-opt label=disable \
    ghcr.io/foxws/laravel-installer:2
```

The workspace is mounted at `/workspace`, and the app's `.env` is read from `/workspace/.env`. `PODMAN_WORKING_PATH` is the workspace's path on the host, which is written into the rendered units. To read another `.env`, mount it and point `INSTALLER_ENV_FILE` to it, either as an absolute path or relative to the workspace (such as `.env.production`). For another mount point, set `INSTALLER_WORKSPACE`. `lpod installer IMAGE --workspace PATH` sets all of this up for you.

### With lpod

[`lpod`](https://github.com/foxws/lpod) can start it for you with `lpod installer IMAGE`. It also starts the Podman socket when it isn't running.

Every image is built with provenance. Check where it came from with:

```bash
gh attestation verify oci://ghcr.io/foxws/laravel-installer:2 --owner foxws
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
