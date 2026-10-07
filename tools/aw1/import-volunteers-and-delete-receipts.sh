#!/usr/bin/env bash
# Run on your Mac. Imports the 1.0 volunteers into animalwrites.in, then replaces the 1.0 archive with one that
# has no receipt records, and deletes the old archive files only after the new one checks out.
# You type the archive passphrase (gpg asks); nothing personal is printed, only counts.
set -euo pipefail
DIR="$HOME/Archives/animal-writes-1.0"
OLD="$DIR/animal-writes-1.0-mine.json.gpg"
RAW="$DIR/kv_store_7a8516f4.json.gpg"
NEW="$DIR/animal-writes-1.0-volunteers.json.gpg"
TOOL="$(cd "$(dirname "$0")" && pwd)/aw1_archive.py"
HOST="ssh -i $HOME/.ssh/hostinger_aw -p 65002 u544169507@46.28.45.135"

echo "1/4  Importing 1.0 volunteers into animalwrites.in (counts only)"
gpg --quiet -d "$OLD" | python3 "$TOOL" volunteers | $HOST 'cd ~/domains/animalwrites.in/public_html && wp aw-volunteers import --source=v1_campaign'

echo "2/4  Writing a new archive without receipt records (gpg asks for a passphrase for the NEW file)"
gpg --quiet -d "$OLD" | python3 "$TOOL" strip-receipts | gpg --symmetric --cipher-algo AES256 -o "$NEW"
chmod 600 "$NEW"

echo "3/4  Checking the new archive"
CHECK=$(gpg --quiet -d "$NEW" | python3 "$TOOL" count 2>&1 >/dev/null)
echo "     $CHECK"
case "$CHECK" in
  *"'receipt'"*) echo "STOP: the new archive still has receipt records. Old files kept."; exit 1;;
  *"'volunteer': 1467"*) ;;
  *) echo "STOP: the new archive does not have the 1,467 volunteers. Old files kept."; exit 1;;
esac

echo "4/4  Deleting the old archive files that contain receipts"
rm -P "$OLD" "$RAW" 2>/dev/null || rm -f "$OLD" "$RAW"
ls -l "$DIR"
echo "Done. Receipt records deleted; 1.0 volunteers kept in $NEW and in the site's volunteers table."
