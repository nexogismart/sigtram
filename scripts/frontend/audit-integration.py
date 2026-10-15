"""Prove the integrated presentation preserves the pinned backend implementation."""
from pathlib import Path
import argparse
import hashlib
import json
import re
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('ref', nargs='?', default='7b095bef')
parser.add_argument('--reviewed-fixes', action='store_true', help='Also verify explicitly documented fixes by their exact SHA-256.')
args = parser.parse_args()
REF = args.ref
reviewed_manifest = {}
if args.reviewed_fixes:
    manifest = json.loads((ROOT / 'docs/frontend/integration-reviewed-fixes.json').read_text(encoding='utf-8'))
    if manifest['backend_ref'] != REF:
        raise SystemExit('Reviewed fixes belong to a different backend reference.')
    reviewed_manifest = manifest['files']

def git(*args):
    return subprocess.check_output(['git', *args], cwd=ROOT)

files = git('ls-tree', '-r', '--name-only', REF).decode().splitlines()
prefixes = ('resources/views/mod_gene/mod_tram_inter/', 'resources/views/mod_gm/mod_tram_exte/')
failures = []
checked = 0
reviewed = []
for name in files:
    if not (name.startswith(prefixes) or name.startswith(('app/', 'resources/js/', 'resources/css/')) or name == 'routes/web.php'):
        continue
    if not (ROOT / name).exists():
        failures.append(name + ': missing backend file')
        continue
    actual = (ROOT / name).read_bytes().replace(b'\r\n', b'\n')
    expected = git('show', f'{REF}:{name}').replace(b'\r\n', b'\n')
    if name.startswith(prefixes):
        actual = re.sub(rb'\n\{\{-- SIGTRAM integration: presentation only --\}\}.*?\{\{-- /SIGTRAM integration --\}\}\n', b'', actual, flags=re.S)
    elif name == 'app/Services/Core/SecurityCore.php':
        actual = re.sub(rb"^\s*'id' => (?:\(int\) \$hijo->cdgo_rle|\$padreId),\r?\n", b'', actual, flags=re.M)
    elif name == 'routes/web.php':
        actual = re.sub(rb"    Route::get\('/perfil/foto'.*?->name\('perfil.foto'\);\r?\n", b'', actual, flags=re.S)
    if actual != expected:
        if reviewed_manifest.get(name) == hashlib.sha256(actual).hexdigest():
            reviewed.append(name)
        else:
            failures.append(name)
    checked += 1

# Backend-deleted screens must stay deleted; never revive old functionality.
base_files = git('ls-tree', '-r', '--name-only', '62bee7bd').decode().splitlines()
for name in base_files:
    if name.startswith(prefixes) and name not in files and (ROOT / name).exists():
        failures.append(name + ': deleted by backend but restored')
unused = set(reviewed_manifest) - set(reviewed)
failures.extend(name + ': stale reviewed entry' for name in sorted(unused))
print(json.dumps({'backend_ref': REF, 'checked': checked, 'reviewed_fixes': reviewed, 'failures': failures}, indent=2))
sys.exit(bool(failures))
