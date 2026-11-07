#!/usr/bin/env python3
"""Build deployable theme/plugin ZIPs. No credentials, WordPress core, database or demo import."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib
import json

backend=Path(__file__).resolve().parents[1]
repo=backend.parent
dest=backend/'dist';dest.mkdir(exist_ok=True)
manifest={}
for kind,slug in [('themes','iran-grains'),('plugins','iran-grains-core')]:
    source=backend/'wp-content'/kind/slug
    out=dest/(slug+'.zip')
    with ZipFile(out,'w',ZIP_DEFLATED) as z:
        for file in sorted(source.rglob('*')):
            if file.is_file():z.write(file,Path(slug)/file.relative_to(source))
        if kind=='themes':
            for file in sorted((repo/'Site').rglob('*')):
                if file.is_file() and (file.suffix=='.html' and file.name!='iran-grains-standalone.html' or 'assets' in file.relative_to(repo/'Site').parts):
                    z.write(file,Path(slug)/'prototype'/file.relative_to(repo/'Site'))
    manifest[out.name]=hashlib.sha256(out.read_bytes()).hexdigest()
(dest/'sha256.json').write_text(json.dumps(manifest,indent=2))
print('Built theme and plugin in Backend/dist; credentials and demo data excluded.')
