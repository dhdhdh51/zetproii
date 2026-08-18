#!/bin/bash
#
# BharatSEO deploy script
# =====================================================================
# Copies this repository onto a live site without touching anything the site
# generated itself. Built for CyberPanel (which has no deploy manifest of its
# own), but it is plain bash and works anywhere you have SSH: CyberPanel,
# cPanel Terminal, a VPS, EC2.
#
#   Usage:  ./deploy.sh /home/<your-domain>/public_html
#
# On CyberPanel the document root is normally:
#   /home/<domain>/public_html          e.g. /home/cvbuilder.bharatseo.site/public_html
# Confirm it in CyberPanel > Websites > List Websites > Manage ("Document Root").
#
# ---------------------------------------------------------------------
# NEVER TOUCHED (these live on the server, not in git):
#   env.php / .env          your database credentials and APP_KEY
#   storage/                logs, cache, sessions, uploads
#   public/uploads/         customer uploads
#   storage/.installed      the installer lock
#
# NEVER COPIED:
#   .git, deploy.sh, testenv, and install.php once the site is installed
#     (so a deploy can't put the installer back onto a live site)
# =====================================================================
set -euo pipefail

DEST="${1:-}"
SRC="$(cd "$(dirname "$0")" && pwd)"

die() { echo "ERROR: $*" >&2; exit 1; }
say() { echo "  $*"; }

[ -n "$DEST" ] || die "No document root given.
  Usage: ./deploy.sh /home/<your-domain>/public_html"
[ -d "$DEST" ] || die "'$DEST' does not exist. Check the Document Root in CyberPanel > Websites > Manage."
[ -f "$SRC/index.php" ] && [ -d "$SRC/app" ] || die "'$SRC' does not look like the BharatSEO repository."

# Refuse to deploy onto itself, which would race the copy against its own source.
[ "$(cd "$SRC" && pwd -P)" != "$(cd "$DEST" && pwd -P)" ] || die "Source and destination are the same directory.
  Clone the repo somewhere else (e.g. ~/bharatseo-repo) and deploy from there."

echo ""
echo "Deploying BharatSEO"
echo "  from : $SRC"
echo "  to   : $DEST"
echo ""

# --- pull the latest code, if this is a git checkout -------------------------
if [ -d "$SRC/.git" ]; then
    if git -C "$SRC" diff --quiet && git -C "$SRC" diff --cached --quiet; then
        say "Fetching latest code..."
        git -C "$SRC" pull --ff-only || die "git pull failed. Resolve it, then re-run."
        say "Now at: $(git -C "$SRC" log --oneline -1)"
    else
        say "Local changes present - skipping git pull, deploying the working tree as-is."
    fi
else
    say "Not a git checkout - deploying these files as they are."
fi
echo ""

# --- work out whether the installer should be withheld -----------------------
INSTALLED=no
[ -f "$DEST/storage/.installed" ] && INSTALLED=yes

# --- copy ---------------------------------------------------------------------
# Excludes are the important part: no --delete, and every server-owned path is
# protected, so a deploy can never wipe credentials or customer files.
EXCLUDES=(
    --exclude='.git/'
    --exclude='.gitignore'
    --exclude='deploy.sh'
    --exclude='testenv/'
    --exclude='env.php'
    --exclude='.env'
    --exclude='storage/logs/'
    --exclude='storage/cache/'
    --exclude='storage/sessions/'
    --exclude='storage/uploads/'
    --exclude='public/uploads/'
)
[ "$INSTALLED" = "yes" ] && EXCLUDES+=(--exclude='install.php')

if command -v rsync >/dev/null 2>&1; then
    say "Copying with rsync..."
    rsync -a "${EXCLUDES[@]}" "$SRC"/ "$DEST"/
else
    # Fallback for hosts without rsync: tar respects the same exclusions and
    # preserves the dotfiles (.htaccess) that GUI upload tools tend to skip.
    say "rsync not available - copying with tar..."
    TAR_EXCLUDES=()
    for e in "${EXCLUDES[@]}"; do
        TAR_EXCLUDES+=("--exclude=${e#--exclude=}")
    done
    ( cd "$SRC" && tar cf - "${TAR_EXCLUDES[@]}" . ) | ( cd "$DEST" && tar xf - )
fi

# --- writable directories -----------------------------------------------------
say "Ensuring writable directories exist..."
mkdir -p "$DEST"/storage/{logs,cache,sessions,uploads} "$DEST"/public/uploads
chmod -R 755 "$DEST"/storage "$DEST"/public/uploads

# --- report -------------------------------------------------------------------
echo ""
echo "Done. $(find "$DEST" -type f | wc -l) files in the document root."
echo ""
if [ ! -f "$DEST/env.php" ] && [ ! -f "$DEST/.env" ]; then
    echo "  NEXT: no env.php found - open https://your-domain/install.php to finish setup."
elif [ "$INSTALLED" = "yes" ]; then
    echo "  install.php was withheld (this site is already installed)."
fi
echo "  Verify:  https://your-domain/diagnose.php   (expect no FAIL rows)"
echo "  Asset URLs carry ?v=<version>, so browsers pick up changes without a hard refresh."
echo ""
