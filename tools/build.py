"""Build the runtime-only WordPress plugin ZIP without development files."""

from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED

root = Path(__file__).resolve().parent.parent
destination = root / 'dist' / 'mx-backbone.zip'
destination.parent.mkdir(exist_ok=True)
sources = [root / 'mx-backbone.php']
for directory in ('includes', 'modules'):
    sources.extend(sorted((root / directory).rglob('*.php')))
asset_extensions = {'.css', '.js'}
sources.extend(
    path for path in sorted((root / 'assets').rglob('*'))
    if path.is_file() and path.suffix.lower() in asset_extensions
)
with ZipFile(destination, 'w', ZIP_DEFLATED) as archive:
    for source in sources:
        archive.write(source, Path('mx-backbone') / source.relative_to(root))
print(destination)
