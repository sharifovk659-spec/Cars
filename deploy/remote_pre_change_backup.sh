#!/bin/bash
set -euo pipefail

STAMP=$(date +%Y%m%d_%H%M%S)
PRIVATE=/home/u417315406/domains/inovaauto.com/private/carsbot
APP=/home/u417315406/domains/inovaauto.com/public_html/carsbot
BDIR="${PRIVATE}/backups/pre_search_filters_${STAMP}"
mkdir -p "$BDIR"
export BDIR
export APP

# Dump via app PDO (same credentials the site uses)
php <<'PHP'
<?php
$outDir = getenv('BDIR') ?: '';
$app = getenv('APP') ?: '';
if ($outDir === '' || $app === '') {
    fwrite(STDERR, "missing env\n");
    exit(1);
}
require $app . '/config/database.php';
$pdo = db();
$out = rtrim($outDir, '/') . '/database.sql';
$fh = fopen($out, 'wb');
if ($fh === false) {
    fwrite(STDERR, "cannot write sql\n");
    exit(1);
}
fwrite($fh, "-- Telegram Cars backup " . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    $table = (string) $table;
    $safe = str_replace('`', '``', $table);
    $create = $pdo->query("SHOW CREATE TABLE `{$safe}`")->fetch(PDO::FETCH_NUM);
    fwrite($fh, "DROP TABLE IF EXISTS `{$safe}`;\n");
    fwrite($fh, ($create[1] ?? '') . ";\n\n");
    $rows = $pdo->query("SELECT * FROM `{$safe}`");
    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
        $cols = [];
        $vals = [];
        foreach ($row as $col => $value) {
            $cols[] = '`' . str_replace('`', '``', (string) $col) . '`';
            $vals[] = $value === null ? 'NULL' : $pdo->quote((string) $value);
        }
        fwrite($fh, 'INSERT INTO `' . $safe . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n");
    }
    fwrite($fh, "\n");
}
fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($fh);
echo 'db_bytes=' . filesize($out) . PHP_EOL;
echo 'tables=' . count($tables) . PHP_EOL;
PHP

tar -czf "${BDIR}/files_snapshot.tgz" \
  -C "$APP" \
  --exclude='.git' \
  --exclude='uploads/cache' \
  --exclude='backups' \
  .

cat > "${BDIR}/meta.txt" <<EOF
stamp=${STAMP}
path=${BDIR}
app=${APP}
method=pdo+tar
EOF

echo "BACKUP_OK ${BDIR}"
ls -lh "$BDIR"
