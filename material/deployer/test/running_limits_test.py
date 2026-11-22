"""Exercise the host-side configuration transaction using an isolated Compose stub."""
import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

DEPLOYER = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(DEPLOYER / "src" / "lib"))
import running_limits


class HostTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.home = Path(self.temp.name)
        self.folder = self.home / "material-moodle" / "material" / "docker"
        self.folder.mkdir(parents=True)
        self.envfile = self.folder / ".env"
        self.original = b"# preserve this line\nMOODLE_PORT=8090\n"
        self.envfile.write_bytes(self.original)
        self.schema = json.loads((DEPLOYER / "stack" / "php-settings.json").read_text())
        self.rejectread = False
        self.restarts = 0

    def docker(self, args, **options):
        if args[1] == "ps":
            return "web"
        if args[1] == "inspect" and args[3] == "{{json .Config.Labels}}":
            return json.dumps({"com.docker.compose.project.working_dir": str(self.folder),
                "com.docker.compose.project.config_files": str(self.folder / "docker-compose.yml")})
        if args[1] == "inspect":
            return "sha256:fixture material-moodle:fixture"
        if args[1] == "image":
            return "sha256:fixture"
        if args[1] == "compose" and "ps" in args:
            return {"moodle": "web", "cron": "worker", "db": "db"}[args[-1]]
        if args[1] == "compose" and "up" in args:
            self.restarts += 1
            return ""
        if "cat" in args:
            return json.dumps(self.schema)
        if "-e" in args:
            return ""
        if args[1] == "exec":
            if self.rejectread and self.restarts == 1:
                raise ValueError("Deliberate fixture readback failure")
            values = {k: v["default"] for k, v in self.schema.items()}
            if b"MATERIAL_PHP_MEMORY_LIMIT=768M" in self.envfile.read_bytes():
                values["memory_limit"] = "768M"
            return json.dumps(values)
        self.fail(args)

    def apply(self):
        preview = subprocess.CompletedProcess([], 0, stdout="", stderr=" Container material-moodle-moodle-1 Running\n")
        with patch.object(running_limits, "command", self.docker), patch.object(subprocess, "run", return_value=preview):
            return running_limits.main(self.home, "apply", {"memory_limit": "768M"})

    def test_apply_and_repeat(self):
        self.assertEqual(self.apply()["memory_limit"], "768M")
        self.assertEqual(self.envfile.read_bytes(), self.original + b"MATERIAL_PHP_MEMORY_LIMIT=768M\n")
        self.apply()
        self.assertEqual(self.restarts, 1)

    def test_rollback_and_recovery_record(self):
        self.rejectread = True
        with self.assertRaisesRegex(ValueError, "previous configuration was restored"):
            self.apply()
        self.assertEqual(self.original, self.envfile.read_bytes())
        self.assertEqual(self.restarts, 2)
        recovery = self.folder / ".material-php-limits.previous"
        self.assertFalse(recovery.exists())
        recovery.write_bytes(self.original)
        with self.assertRaisesRegex(ValueError, "previous update needs recovery"):
            self.apply()
        self.assertEqual(self.restarts, 2)


if __name__ == "__main__":
    unittest.main()
