"""Prove the integrated presentation preserves the pinned backend implementation."""
from pathlib import Path
import json
import re
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
REF = sys.argv[1] if len(sys.argv) > 1 else '7b095bef'

def git(*args):
    return subprocess.check_output(['git', *args], cwd=ROOT)

files = git('ls-tree', '-r', '--name-only', REF).decode().splitlines()
prefixes = ('resources/views/mod_gene/mod_tram_inter/', 'resources/views/mod_gm/mod_tram_exte/')
failures = []
checked = 0
for name in files:
    if not (name.startswith(prefixes) or name.startswith(('app/', 'resources/js/', 'resources/css/')) or name == 'routes/web.php'):
        continue
    actual = (ROOT / name).read_bytes()
    expected = git('show', f'{REF}:{name}')
    if name.startswith(prefixes):
        actual = re.sub(rb'\n\{\{-- SIGTRAM integration: presentation only --\}\}.*?\{\{-- /SIGTRAM integration --\}\}\n', b'', actual, flags=re.S)
    elif name == 'app/Services/Core/SecurityCore.php':
        actual = re.sub(rb"^\s*'id' => (?:\(int\) \$hijo->cdgo_rle|\$padreId),\r?\n", b'', actual, flags=re.M)
    elif name == 'routes/web.php':
        actual = re.sub(rb"    Route::get\('/perfil/foto'.*?->name\('perfil.foto'\);\r?\n", b'', actual, flags=re.S)
    if actual != expected:
        failures.append(name)
    checked += 1

# Backend-deleted screens must stay deleted; never revive old functionality.
base_files = git('ls-tree', '-r', '--name-only', '62bee7bd').decode().splitlines()
for name in base_files:
    if name.startswith(prefixes) and name not in files and (ROOT / name).exists():
        failures.append(name + ': deleted by backend but restored')
print(json.dumps({'backend_ref': REF, 'checked': checked, 'failures': failures}, indent=2))
sys.exit(bool(failures))
