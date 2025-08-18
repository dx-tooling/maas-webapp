#!/usr/bin/env bash
set -euo pipefail

# Allowed subcommands and container name format
ALLOWED_CMDS=("start" "stop" "restart" "rm" "inspect" "ps" "run" "exec")
CONTAINER_RE='^mcp-instance-[a-zA-Z0-9]+$'

# Docker binary (overridable for validation output)
DOCKER_BIN="${DOCKER_BIN:-/usr/bin/docker}"

# Exec helper: in validation mode, print and exit 0; otherwise exec docker
do_exec() {
  if [[ "${MAAS_WRAPPER_VALIDATE_ONLY:-}" == "1" ]]; then
    printf '%s' "${DOCKER_BIN}"
    for arg in "$@"; do
      printf ' %q' "${arg}"
    done
    echo
    exit 0
  fi
  exec "${DOCKER_BIN}" "$@"
}

cmd="${1:-}"; shift || true
case " ${ALLOWED_CMDS[*]} " in
  *" ${cmd} "*) ;;
  *) echo "Denied: cmd not allowed"; exit 1 ;;
esac

# Commands that require a container name (handled generically), excluding
# subcommands with special argument parsing (run, exec, inspect)
if [[ "${cmd}" != "ps" ]] && [[ "${cmd}" != "run" ]] && [[ "${cmd}" != "exec" ]] && [[ "${cmd}" != "inspect" ]]; then
  name="${1:-}"; shift || true
  [[ -n "${name:-}" && "${name}" =~ ${CONTAINER_RE} ]] || { echo "Denied: invalid container name"; exit 1; }
fi

# Execute restricted docker commands
case "${cmd}" in
  ps)        do_exec ps --filter "name=^/mcp-instance-" --format '{{.Names}} {{.Status}}' ;;
  inspect)   {
    # Determine container name as the last non-flag argument
    args=("$@")
    container_name=""
    for (( i=${#args[@]}-1; i>=0; i-- )); do
      arg="${args[$i]}"
      if [[ ! "${arg}" =~ ^- ]] && [[ -n "${arg}" ]]; then
        container_name="${arg}"
        break
      fi
    done
    [[ -n "${container_name}" && "${container_name}" =~ ${CONTAINER_RE} ]] || { echo "Denied: invalid or missing container name for inspect"; exit 1; }
    do_exec inspect "$@"
  } ;;
  start)     do_exec start "${name}" ;;
  stop)      do_exec stop "${name}" ;;
  restart)   do_exec restart "${name}" ;;
  rm)        do_exec rm "${name}" ;;
  exec)      {
    # docker exec [OPTIONS] CONTAINER COMMAND [ARG...]
    # Find the first non-flag argument as the container name
    args=("$@")
    container_name=""
    for arg in "${args[@]}"; do
      if [[ ! "${arg}" =~ ^- ]]; then
        container_name="${arg}"
        break
      fi
    done
    [[ -n "${container_name}" && "${container_name}" =~ ${CONTAINER_RE} ]] || { echo "Denied: invalid or missing container name for exec"; exit 1; }
    do_exec exec "$@"
  } ;;
  run)       {
    # For run command, validate that the image is maas-mcp-instance
    # Extract container name from --name parameter and image name from arguments
    container_name=""
    image_name=""
    args=("$@")
    
    # Find the container name from --name parameter
    for i in "${!args[@]}"; do
      if [[ "${args[$i]}" == "--name" ]] && [[ $((i+1)) -lt ${#args[@]} ]]; then
        container_name="${args[$((i+1))]}"
        break
      fi
    done
    
    # Validate container name
    if [[ -z "$container_name" ]] || [[ ! "$container_name" =~ ${CONTAINER_RE} ]]; then
      echo "Denied: invalid or missing container name (use --name mcp-instance-*)"
      exit 1
    fi
    
    # Determine image name as the last non-flag argument
    for (( i=${#args[@]}-1; i>=0; i-- )); do
      arg="${args[$i]}"
      if [[ ! "$arg" =~ ^- ]] && [[ -n "$arg" ]]; then
        image_name="$arg"
        break
      fi
    done
    
    # Validate image name
    if [[ -z "$image_name" ]] || [[ "$image_name" == -* ]] || [[ "$image_name" != "maas-mcp-instance" ]]; then
      echo "Denied: only maas-mcp-instance image is allowed"
      exit 1
    fi
    
    do_exec run "$@"
  } ;;
esac
