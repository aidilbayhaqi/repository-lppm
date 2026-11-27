#!/usr/bin/env bash
# Rollback helper — audit/p1-fixes
#
# Semua perbaikan ada di branch `audit/p1-fixes`.
# Branch `main` TIDAK disentuh, jadi rollback selalu tersedia.
#
# Pakai:
#   ./tools/rollback-audit.sh status    # cek posisi sekarang
#   ./tools/rollback-audit.sh list      # daftar commit perbaikan
#   ./tools/rollback-audit.sh one <sha> # batalkan satu commit
#   ./tools/rollback-audit.sh last      # batalkan commit terakhir
#   ./tools/rollback-audit.sh all       # kembali ke main, hapus branch
#   ./tools/rollback-audit.sh keep      # tetap di branch, simpan sebagai patch

set -euo pipefail

BRANCH="audit/p1-fixes"
BASE="58316ad"
PATCH_OUT="audit-p1-fixes.patch"

cd "$(git rev-parse --show-toplevel)"

usage() {
    sed -n '2,14p' "$0" | sed 's/^# \{0,1\}//'
    exit 1
}

cmd_status() {
    echo "branch   : $(git branch --show-current)"
    echo "head     : $(git rev-parse --short HEAD)"
    echo "base     : $BASE (main)"
    echo "commits  : $(git rev-list --count "$BASE..HEAD" 2>/dev/null || echo 0) di atas base"
    echo
    echo "--- working tree ---"
    git status --short || true
    echo
    if [ -z "$(git status --porcelain)" ]; then
        echo "bersih"
    else
        echo "ADA PERUBAHAN BELUM DI-COMMIT"
    fi
}

cmd_list() {
    echo "commit perbaikan di atas $BASE:"
    echo
    git log --oneline --no-decorate "$BASE..HEAD" 2>/dev/null || echo "(belum ada)"
}

cmd_one() {
    local sha="${1:-}"
    [ -z "$sha" ] && { echo "butuh sha commit"; exit 1; }
    echo "membatalkan $sha ..."
    git revert --no-edit "$sha"
    echo "selesai. jalankan 'php artisan test' untuk verifikasi."
}

cmd_last() {
    local sha
    sha="$(git rev-parse HEAD)"
    if [ "$sha" = "$(git rev-parse "$BASE")" ]; then
        echo "sudah di base, tidak ada yang bisa dibatalkan"
        exit 0
    fi
    echo "membatalkan commit terakhir: $(git log -1 --oneline)"
    git revert --no-edit HEAD
    echo "selesai."
}

cmd_all() {
    echo "PERINGATAN: ini membuang seluruh branch $BRANCH."
    if [ -n "$(git status --porcelain)" ]; then
        echo "working tree kotor — commit atau stash dulu."
        exit 1
    fi
    read -r -p "lanjut? ketik 'ya': " answer
    [ "$answer" = "ya" ] || { echo "dibatalkan."; exit 0; }

    git checkout -q main
    git branch -D "$BRANCH"
    echo "kembali ke main di $(git rev-parse --short HEAD)."
    echo "branch $BRANCH dihapus. Semua perubahan hilang, main utuh."
}

cmd_keep() {
    git diff "$BASE"..HEAD > "$PATCH_OUT"
    echo "patch tersimpan: $PATCH_OUT"
    echo "  ($(wc -l < "$PATCH_OUT") baris, $(git rev-list --count "$BASE..HEAD") commit)"
    echo
    echo "cara pakai di mesin lain:"
    echo "  git checkout main"
    echo "  git checkout -b audit/p1-fixes"
    echo "  git apply $PATCH_OUT"
}

case "${1:-}" in
    status) cmd_status ;;
    list)   cmd_list ;;
    one)    cmd_one "${2:-}" ;;
    last)   cmd_last ;;
    all)    cmd_all ;;
    keep)   cmd_keep ;;
    *)      usage ;;
esac
