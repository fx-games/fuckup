#!/bin/sh
# Deploys the working tree to https://ruzzoli.de/fuckup/ via `ssh ruzzoli.de` (needs sudo there).
# Player state on the server (DB, user dirs, unlock markers, command log) is never overwritten.
set -e
cd "$(dirname "$0")"
HOST=ruzzoli.de
DEST=/var/www/ruzzoli.de/fuckup
STAGE=fuckup-stage

# production DB: intel entries only plus guest, none of the old player accounts
TMP=$(mktemp -d)
cp xterm/niswes.db "$TMP/niswes.db"
sqlite3 "$TMP/niswes.db" "DELETE FROM users WHERE username <> 'guest'; VACUUM;"
: > "$TMP/termAccessLog.txt"

git ls-files -co --exclude-standard | grep -vE '^(router\.php|start\.sh|deploy\.sh|xterm/niswes\.db|webStats/termAccessLog\.txt|xterm/sub/packer\.php|nginx-fuckup\.conf)$|\.htaccess$' \
  | rsync -a --files-from=- . "$HOST:$STAGE/"
rsync -a nginx-fuckup.conf "$TMP/niswes.db" "$TMP/termAccessLog.txt" "$HOST:$STAGE-extra/"
rm -r "$TMP"

ssh "$HOST" sh -s <<REMOTE
set -e
dpkg -s php8.3-sqlite3 >/dev/null 2>&1 || { sudo apt-get install -y -q php8.3-sqlite3 && sudo systemctl reload php8.3-fpm; }
sudo mkdir -p $DEST/xterm $DEST/webStats
sudo rsync -a --chown=root:www-data --chmod=D755,F644 $STAGE/ $DEST/
sudo rsync -a --ignore-existing --chown=www-data:www-data --chmod=F640 $STAGE-extra/niswes.db $DEST/xterm/
sudo rsync -a --ignore-existing --chown=www-data:www-data --chmod=F640 $STAGE-extra/termAccessLog.txt $DEST/webStats/
# writable by PHP: DB (+ its dir for SQLite's journal), player dirs, the command log
sudo chown www-data $DEST/xterm
sudo chown -R www-data $DEST/xterm/network/MainFrame
sudo install -m 644 $STAGE-extra/nginx-fuckup.conf /etc/nginx/snippets/fuckup.conf
echo 'limit_req_zone \$binary_remote_addr zone=fuckup:1m rate=5r/s;' | sudo tee /etc/nginx/conf.d/fuckup-zone.conf >/dev/null
SITE=/etc/nginx/sites-enabled/ruzzoli.de.conf
sudo cp \$SITE /root/ruzzoli.de.conf.before-fuckup
grep -q 'snippets/fuckup.conf' \$SITE || \
  sudo sed -i 's#^    include /etc/nginx/snippets/roguelikes-beacon.conf;#&\n    include /etc/nginx/snippets/fuckup.conf;#' /etc/nginx/sites-enabled/ruzzoli.de.conf
sudo nginx -t || { sudo cp /root/ruzzoli.de.conf.before-fuckup \$SITE; echo 'nginx config rejected, restored'; exit 1; }
sudo systemctl reload nginx
rm -rf $STAGE $STAGE-extra
REMOTE
echo "Deployed: https://$HOST/fuckup/"
