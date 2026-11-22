"""Read or apply the finite PHP settings of an existing Material Moodle stack over SSH."""
import fcntl
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile


def command(args, *, cwd=None, timeout=30):
    result = subprocess.run(args, cwd=cwd, capture_output=True, text=True, timeout=timeout)
    if result.returncode:
        # Never forward a Compose environment, which may contain credentials.
        raise ValueError(f"{args[0]} {args[1]} failed with exit code {result.returncode}")
    return result.stdout.strip()


def replace_bytes(path, expected, replacement):
    if path.read_bytes() != expected:
        raise ValueError("The configuration changed during this operation. Reload the server settings.")
    previous = path.stat()
    with tempfile.NamedTemporaryFile(prefix=".material-php-limits.", dir=path.parent, delete=False) as output:
        temp = Path(output.name)
        try:
            output.write(replacement)
            output.flush()
            os.fsync(output.fileno())
            if os.geteuid() == 0:
                os.fchown(output.fileno(), previous.st_uid, previous.st_gid)
            os.fchmod(output.fileno(), previous.st_mode & 0o660)
            os.replace(temp, path)
        finally:
            temp.unlink(missing_ok=True)


def main(home, action, requested, *, project="material-moodle"):
    filters = ["--filter", "label=com.docker.compose.project=" + project]
    ids = command(["docker", "ps", "-q", *filters, "--filter", "label=com.docker.compose.service=moodle"]).splitlines()
    if len(ids) != 1:
        raise ValueError("Expected one running Material Moodle web container on this host.")
    labels = json.loads(command(["docker", "inspect", "--format", "{{json .Config.Labels}}", ids[0]]))
    parent = Path(home).resolve() / "material-moodle"
    directory = Path(labels["com.docker.compose.project.working_dir"]).resolve(strict=True)
    if directory not in (parent, parent / "material" / "docker"):
        raise ValueError("The running stack uses a directory outside the supported deployment layouts.")
    files = [Path(p) for p in labels["com.docker.compose.project.config_files"].split(",")]
    supported = {"docker-compose.yml", "build.override.yml", "namedtunnel.override.yml"}
    if not files or any(p.parent != directory or p.name not in supported or p.is_symlink() for p in files):
        raise ValueError("The running stack uses an unrecognised Compose file.")
    compose = ["docker", "compose", "-p", project]
    for file in files:
        compose.extend(["-f", str(file)])
    envfile = directory / ".env"
    if envfile.is_symlink() or not envfile.is_file():
        raise ValueError("Expected a regular deployment .env file.")

    def service(name):
        return command([*compose, "ps", "-q", name], cwd=directory)

    def effective(container, names):
        php = """$all=parse_ini_file('/usr/local/etc/php/conf.d/zz-material-limits.ini', false, INI_SCANNER_RAW);
            foreach (array_slice($argv,1) as $k) {$v[$k]=$all[$k];} echo json_encode($v);"""
        return json.loads(command(["docker", "exec", container, "php", "-r", php, "--", *names]))

    lock = os.open(directory, os.O_RDONLY)
    try:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        recovery = directory / ".material-php-limits.previous"
        if action == "apply" and os.path.lexists(recovery):
            raise ValueError("A previous update needs recovery. Inspect .material-php-limits.previous on the host before applying again.")
        web = service("moodle")
        if not web.startswith(ids[0]):
            raise ValueError("The active web container changed; reload the server settings.")
        schema = json.loads(command(["docker", "exec", web, "cat", "/usr/local/share/material-runtime/php-settings.json"]))
        before = effective(web, schema)
        if action == "read":
            return before
        if action != "apply" or not requested:
            raise ValueError("Select at least one PHP setting to apply.")
        if any(k not in schema or v not in schema[k]["options"] or not re.fullmatch(r"[0-9]+M?", v)
               for k, v in requested.items()):
            raise ValueError("The selected choice is unsupported by the running image.")
        # Never turn a settings operation into an image upgrade or a database restart.
        preview = subprocess.run([*compose, "--ansi", "never", "--dry-run", "up", "-d", "--no-build",
                                  "--no-deps", "--pull", "never", "moodle", "cron"],
                                 cwd=directory, capture_output=True, text=True, timeout=30)
        rows = (preview.stdout + preview.stderr).strip().splitlines()
        if preview.returncode or not rows or not all(
                re.fullmatch(r"\s*(?:DRY-RUN MODE\s*[-:]?\s*)?Container\s+\S+\s+Running\s*", row) for row in rows):
            raise ValueError("The existing Compose configuration has pending changes or cannot be previewed.")
        db = service("db")
        targets = ["moodle", "cron"]
        if not db:
            raise ValueError("The database is not running.")
        for name in targets:
            identity = command(["docker", "inspect", "--format", "{{.Image}} {{.Config.Image}}", service(name)])
            running, tag = identity.split()
            selected = command(["docker", "image", "inspect", "--format", "{{.Id}}", tag])
            if running != selected:
                raise ValueError("An image change is pending; finish the deployment before changing PHP limits.")
        overrides = {("MATERIAL_PHP_" + k.replace(".", "_").upper()): v for k, v in requested.items()}
        validate = ["docker", "exec"]
        for k, v in overrides.items():
            validate.extend(["-e", k + "=" + v])
        command(validate + [web, "php", "/usr/local/share/material-runtime/configure-php.php"])
        original = envfile.read_bytes()
        if all(before[k] == v for k, v in requested.items()):
            return before
        updated = original
        for k, v in overrides.items():
            pattern = rb"(?m)^[ \t]*(?:export[ \t]+)?" + k.encode() + rb"[ \t]*=[^\r\n]*"
            if len(re.findall(pattern, updated)) > 1:
                raise ValueError("A selected setting has duplicate entries in the deployment configuration.")
            line = (k + "=" + v).encode()
            if re.search(pattern, updated):
                updated = re.sub(pattern, line, updated)
            else:
                updated += (b"" if updated.endswith(b"\n") else b"\n") + line + b"\n"
        restart = [*compose, "up", "-d", "--no-build", "--pull", "never", "--no-deps", "--wait",
                   "--wait-timeout", "180", *targets]
        # An interrupted SSH session leaves an owner-only rollback copy beside
        # the live file. Never replace a prior, unresolved recovery record.
        with tempfile.NamedTemporaryFile(prefix=".material-php-limits.", dir=directory) as output:
            output.write(original)
            output.flush()
            os.fsync(output.fileno())
            os.link(output.name, recovery)
        try:
            replace_bytes(envfile, original, updated)
        except (ValueError, OSError):
            recovery.unlink()
            raise
        try:
            command(restart, cwd=directory, timeout=240)
            after = effective(service("moodle"), schema)
            worker = effective(service("cron"), schema)
            if service("db") != db or any(after[k] != v or worker[k] != v for k, v in requested.items()):
                raise ValueError("The running services did not report the selected settings.")
        except (ValueError, OSError, subprocess.SubprocessError):
            replace_bytes(envfile, updated, original)
            command(restart, cwd=directory, timeout=240)
            recovery.unlink()
            raise ValueError("The update failed. The previous configuration was restored and the services are healthy.")
        recovery.unlink()
        return after
    finally:
        os.close(lock)


if __name__ == "__main__":
    try:
        print(json.dumps(main(sys.argv[1], sys.argv[2], json.loads(sys.argv[3]))))
    except (ValueError, OSError, KeyError, subprocess.SubprocessError) as error:
        print(str(error) if isinstance(error, ValueError) else "Could not read or reconcile the running stack.", file=sys.stderr)
        sys.exit(1)
