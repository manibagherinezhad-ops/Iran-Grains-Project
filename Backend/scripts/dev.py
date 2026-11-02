#!/usr/bin/env python3
"""Prepare only the local development instance. Requires Docker Desktop + Python 3 + Node."""
import argparse
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parents[1]
CORE_VERSION = '7.1.4'
def run(*args, **kwargs):
    return subprocess.run(args, cwd=ROOT, check=True, **kwargs)

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--core-zip', type=Path)
    parser.add_argument('--seed-demo', action='store_true', help='Import prototype content into LOCAL instance only')
    parser.add_argument('--port', type=int, default=8090)
    args = parser.parse_args()
    runtime = ROOT / '.runtime'
    runtime.mkdir(exist_ok=True)
    envfile = ROOT / '.env'
    if not envfile.exists():
        envfile.write_text(f'DB_PASSWORD={secrets.token_hex(24)}\nDB_ROOT_PASSWORD={secrets.token_hex(24)}\nDEV_PORT={args.port}\nLOCAL_UID={os.getuid() if hasattr(os,"getuid") else 1000}\nLOCAL_GID={os.getgid() if hasattr(os,"getgid") else 1000}\n')
        if os.name != 'nt': envfile.chmod(0o600)
    core = runtime / 'wordpress'
    if not (core / 'wp-load.php').exists():
        archive = args.core_zip or runtime / 'core.zip'
        if not args.core_zip:
            urllib.request.urlretrieve(f'https://codeload.github.com/WordPress/WordPress/zip/refs/tags/{CORE_VERSION}', archive)
        with zipfile.ZipFile(archive) as z:
            z.extractall(runtime / 'unpack')
        shutil.move(str(runtime / 'unpack' / f'WordPress-{CORE_VERSION}'), core)
        shutil.rmtree(runtime / 'unpack')
    config = core / 'wp-config.php'
    if not config.exists():
        salts = '\n'.join(f"define('{key}', '{secrets.token_hex(48)}');" for key in ['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT'])
        config.write_text("<?php\nforeach (['DB_HOST','DB_NAME','DB_USER','DB_PASSWORD'] as $key) define($key, getenv($key));\ndefine('DB_CHARSET','utf8mb4');\ndefine('DB_COLLATE','');\n"+salts+"\n$table_prefix='ig_';\ndefine('WP_ENVIRONMENT_TYPE','local');\ndefine('WP_DEBUG',true);\ndefine('WP_DEBUG_LOG',true);\ndefine('WP_DEBUG_DISPLAY',false);\ndefine('DISALLOW_FILE_EDIT',true);\ndefine('WP_AUTO_UPDATE_CORE',false);\ndefine('DISABLE_WP_CRON',true);\nrequire_once __DIR__.'/wp-settings.php';\n")
    (core/'wp-content'/'plugins').mkdir(parents=True, exist_ok=True)
    (core/'wp-content'/'themes').mkdir(parents=True, exist_ok=True)
    run('docker','compose','up','-d','--wait')
    def wp(*parts, **kw):
        return run('docker','compose','exec','-T','web','wp',*parts,**kw)
    installed = subprocess.run(['docker','compose','exec','-T','web','wp','core','is-installed'],cwd=ROOT,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL).returncode == 0
    if not installed:
        credentials={'username':'ig_manager','password':secrets.token_urlsafe(24),'url':f'http://localhost:{args.port}/wp-admin/'}
        private=runtime/'manager.json';private.write_text(json.dumps(credentials,indent=2))
        if os.name!='nt':private.chmod(0o600)
        install = "wp core install --url=http://localhost:"+str(args.port)+" --title='انجمن غلات ایران' --admin_user=ig_manager --admin_email=manager@example.invalid --skip-email --prompt=admin_password"
        run('docker','compose','exec','-T','web','sh','-c',install,input=credentials['password']+'\n',text=True,capture_output=True)
        print('WordPress installed; credentials saved privately.')
    wp('plugin','activate','iran-grains-core')
    wp('theme','activate','iran-grains')
    wp('option','update','timezone_string','Asia/Tehran')
    wp('option','update','blog_public','0')
    wp('rewrite','structure','/%postname%/')
    if args.seed_demo:
        shutil.copyfile(ROOT/'dev'/'fixtures.json', ROOT/'dev'/'seed.json')
        wp('eval-file','/opt/ig/seed.php','--user=ig_manager')
    print(f'Local site: http://localhost:{args.port}/')
    print(f'Local admin: http://localhost:{args.port}/wp-admin/')
    print('Manager credentials are in Backend/.runtime/manager.json (not committed).')

if __name__ == '__main__': main()
