#!/bin/bash
# 简洁版 hook: 把每条命令直接写到 ~/操作日志/今天.log
# 用法: 在每条命令前先调用 llog <command>
# 或: PROMPT_COMMAND 自动调用

LOG_DIR="$HOME/操作日志"
mkdir -p "$LOG_DIR"

llog() {
  local cmd="$1"
  [ -z "$cmd" ] && return
  local ts=$(date "+%H:%M:%S")
  local cwd=$(pwd)
  local user="${USER:-$(whoami 2>/dev/null)}"
  echo "$ts [$user@$(hostname):$cwd] \$ $cmd" >> "$LOG_DIR/$(date +%Y-%m-%d).log"
}

# 自动挂 PROMPT_COMMAND (bash)
if [ -n "$BASH_VERSION" ] && [[ "$PROMPT_COMMAND" != *"llog"* ]]; then
  __log_last() {
    local last
    last=$(history 1 2>/dev/null | sed 's/^ *[0-9]* *//')
    [ -n "$last" ] && [ "$last" != "llog " ] && llog "$last"
  }
  PROMPT_COMMAND="__log_last${PROMPT_COMMAND:+; $PROMPT_COMMAND}"
fi

# zsh
if [ -n "$ZSH_VERSION" ]; then
  precmd_functions+=(llog)
fi
