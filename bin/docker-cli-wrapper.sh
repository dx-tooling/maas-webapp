#!/usr/bin/env bash
set -euo pipefail

# Allowed subcommands and container name format
ALLOWED_CMDS=("start" "stop" "restart" "rm" "inspect" "ps")
CONTAINER_RE='^mcp-instance-[a-zA-Z0-9]+$'

cmd="${1:-}"; shift || true
case " ${ALLOWED_CMDS[*]} " in
  *" ${cmd} "*) ;;
  *) echo "Denied: cmd not allowed"; exit 1 ;;
esac

# Commands that require a container name
if [[ "${cmd}" != "ps" ]]; then
  name="${1:-}"; shift || true
  [[ -n "${name:-}" && "${name}" =~ ${CONTAINER_RE} ]] || { echo "Denied: invalid container name"; exit 1; }
fi

# Execute restricted docker commands
case "${cmd}" in
  ps)        exec /usr/bin/docker ps --filter "name=^/mcp-instance-" --format '{{.Names}} {{.Status}}' ;;
  inspect)   exec /usr/bin/docker inspect --format='{{.State.Status}}' "${name}" ;;
  start)     exec /usr/bin/docker start "${name}" ;;
  stop)      exec /usr/bin/docker stop "${name}" ;;
  restart)   exec /usr/bin/docker restart "${name}" ;;
  rm)        exec /usr/bin/docker rm "${name}" ;;
esac
