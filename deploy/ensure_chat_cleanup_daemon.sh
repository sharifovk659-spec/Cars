#!/bin/bash
# Ensure the 5-minute bot-chat cleanup daemon is running.
set -e
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIR="$ROOT/storage/bot_chats"
PIDFILE="$DIR/daemon.pid"
LOG="$DIR/daemon.log"
PHP_BIN="${PHP_BIN:-php}"

mkdir -p "$DIR"

if [[ -f "$PIDFILE" ]]; then
  OLD_PID="$(cat "$PIDFILE" 2>/dev/null || true)"
  if [[ -n "$OLD_PID" ]] && kill -0 "$OLD_PID" 2>/dev/null; then
    echo "Daemon already running (pid $OLD_PID)"
    exit 0
  fi
fi

nohup "$PHP_BIN" "$ROOT/deploy/bot_chat_cleanup_daemon.php" >>"$LOG" 2>&1 &
echo $! >"$PIDFILE"
echo "Started bot chat cleanup daemon (pid $(cat "$PIDFILE"))"
