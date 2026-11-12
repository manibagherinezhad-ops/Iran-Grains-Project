#!/usr/bin/env python3
"""Back up the LOCAL environment privately; never runs against DirectAdmin."""
from pathlib import Path
import argparse
import datetime
import subprocess
import zipfile
import shutil
import tempfile

root=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser()
parser.add_argument('--restore',type=Path)
parser.add_argument('--confirm-local-restore',action='store_true')
args=parser.parse_args()
def compose(*parts,**kw):
    return subprocess.run(['docker','compose',*parts],cwd=root,check=True,**kw)
check=compose('exec','-T','web','wp','eval','echo wp_get_environment_type();',capture_output=True,text=True)
if check.stdout.strip()!='local':raise SystemExit('Refusing to operate outside the local development instance.')
if args.restore:
    if not args.confirm_local_restore:raise SystemExit('Restore replaces local database/uploads. Pass --confirm-local-restore explicitly.')
    with tempfile.TemporaryDirectory(dir=root/'.runtime') as temp:
        with zipfile.ZipFile(args.restore) as z:
            for entry in z.infolist():
                path=Path(temp)/entry.filename
                if not path.resolve().is_relative_to(Path(temp).resolve()):raise SystemExit('Invalid backup path')
            z.extractall(temp)
        with open(Path(temp)/'database.sql','rb') as stream:
            compose('exec','-T','db','sh','-c','exec mariadb --user="$MARIADB_USER" --password="$MARIADB_PASSWORD" "$MARIADB_DATABASE"',stdin=stream,stdout=subprocess.DEVNULL)
        uploads=root/'.runtime/wordpress/wp-content/uploads'
        if uploads.exists():shutil.rmtree(uploads)
        if (Path(temp)/'uploads').exists():shutil.copytree(Path(temp)/'uploads',uploads)
    print('Local database/uploads restored. Theme and plugin remain versioned in Git.')
else:
    directory=root/'.runtime/backups';directory.mkdir(parents=True,exist_ok=True)
    if directory.stat().st_mode:directory.chmod(0o700)
    stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d-%H%M%S')
    archive=directory/(stamp+'.zip')
    with tempfile.TemporaryDirectory(dir=root/'.runtime') as temp:
        sql=Path(temp)/'database.sql'
        with sql.open('wb') as stream:
            compose('exec','-T','db','sh','-c','exec mariadb-dump --single-transaction --user="$MARIADB_USER" --password="$MARIADB_PASSWORD" "$MARIADB_DATABASE"',stdout=stream)
        with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED) as z:
            z.write(sql,'database.sql')
            uploads=root/'.runtime/wordpress/wp-content/uploads'
            if uploads.exists():
                for file in uploads.rglob('*'):
                    if file.is_file():z.write(file,Path('uploads')/file.relative_to(uploads))
    archive.chmod(0o600)
    print('Private local backup:',archive)
