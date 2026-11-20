"""Build the public catalog asset from the versioned snippet sources."""

import hashlib
import json
from pathlib import Path

root = Path(__file__).resolve().parent.parent
catalog = json.loads((root / 'library/catalog.json').read_text())
toolbar = {
    'name': 'Floating admin toolbar',
    'description': 'Add an auto-hiding toolbar with shortcuts and user and plugin search.',
    'run_at': 'all',
    'versions': {},
}

for revision in sorted((root / 'library/toolbar').iterdir()):
    source = (revision / 'toolbar.php.txt').read_text()
    code = source.removeprefix('<?php\n\n')
    for name, delimiter in [('toolbar.css', 'MX_TOOLBAR_CSS'), ('toolbar.js', 'MX_TOOLBAR_JS')]:
        asset = (revision / name).read_text().rstrip('\n')
        marker = f'/* MX_ASSET:{name} */'
        if code.count(marker) != 1 or delimiter in asset:
            raise ValueError(f'Invalid asset marker for {name} in {revision.name}')
        code = code.replace(marker, f"<<<'{delimiter}'\n{asset}\n{delimiter}\n")
    toolbar['versions'][revision.name] = {
        'sha256': hashlib.sha256(code.encode()).hexdigest(),
        'code': code,
    }

catalog['snippets']['floating-admin-toolbar'] = toolbar
destination = root / 'dist' / 'mx-backbone-library.json'
destination.parent.mkdir(exist_ok=True)
destination.write_text(json.dumps(catalog, indent=2) + '\n')
print(destination)
