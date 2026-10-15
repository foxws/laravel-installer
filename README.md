# podman-installer

A one-time installer for rootless Podman apps: run it, set up, remove it.

You start the installer container once. It walks you through installing an app as rootless Podman Quadlets in your browser, and then it's gone. From then on, systemd runs the app and [`lpod`](https://github.com/foxws/lpod) manages it. The installer doesn't keep running as a platform (like Coolify or Portainer), and it doesn't replace Cockpit.

> [!WARNING]
> Early work in progress. Only the wizard, the manifest and the requirements step exist so far.

## How it works

- **Host checks** come from `lpod doctor --json`. It runs on the host before the installer starts, and its report is mounted into the container (`INSTALLER_DOCTOR_REPORT`, by default `/run/installer/doctor.json`). The installer stops at the requirements step while the report is missing or has problems.
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
| `type` | `content` (default) or `requirements`, which shows the `lpod doctor` checks  |

Raw HTML in the Markdown is stripped. See [`installer/`](installer) for the example.

## Development

```bash
composer install
npm install && npm run build
php artisan serve
```

Run the tests with `vendor/bin/pest`.

## License

MIT. See [LICENSE](LICENSE).
