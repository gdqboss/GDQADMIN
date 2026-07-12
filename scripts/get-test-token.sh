#!/bin/bash
# 长期 test token 缓存（避免 puppeteer/curl 反复登录）
# 用法 1（拿 token）: eval $(bash /root/server/scripts/get-test-token.sh 2>/dev/null) && echo $TOKEN
# 用法 2（直接打印）: bash /root/server/scripts/get-test-token.sh 2>/dev/null

API="${API:-http://localhost:3200}"
PHONE="${PHONE:-18676970008}"
PASS="${PASS:-aaabbb1234}"
TOKEN_FILE="/tmp/test_token_${PHONE}"

check_token() {
  local t="$1"
  [ -z "$t" ] && return 1
  local code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 \
    "$API/api/auth/me" -H "Authorization: Bearer $t" 2>/dev/null)
  [ "$code" = "200" ] && return 0 || return 1
}

# 1. 缓存有效？
if [ -f "$TOKEN_FILE" ]; then
  CACHED=$(cat "$TOKEN_FILE" 2>/dev/null)
  if check_token "$CACHED"; then
    echo "$CACHED" > "$TOKEN_FILE"
    chmod 600 "$TOKEN_FILE"
    if [ -n "$1" ] && [ "$1" = "--export" ]; then
      echo "export TOKEN='$CACHED'"
    else
      echo "$CACHED"
    fi
    exit 0
  fi
  rm -f "$TOKEN_FILE"
fi

# 2. 重新登录
RESP=$(curl -s --max-time 10 -X POST "$API/api/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"phone\":\"$PHONE\",\"password\":\"$PASS\"}" 2>/dev/null)

NEW_TOKEN=$(echo "$RESP" | python3 -c "import sys,json
try:
    d=json.load(sys.stdin).get('data',{})
    print(d.get('token','') or '')
except: pass
" 2>/dev/null)

if [ -z "$NEW_TOKEN" ]; then
  echo "ERROR: login failed. response: ${RESP:0:200}" >&2
  exit 1
fi

echo "$NEW_TOKEN" > "$TOKEN_FILE"
chmod 600 "$TOKEN_FILE"

if [ -n "$1" ] && [ "$1" = "--export" ]; then
  echo "export TOKEN='$NEW_TOKEN'"
else
  echo "$NEW_TOKEN"
fi