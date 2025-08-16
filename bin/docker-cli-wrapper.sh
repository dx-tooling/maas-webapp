#!/usr/bin/env bash
set -euo pipefail

# Allowed subcommands and container name format
ALLOWED_CMDS=("start" "stop" "restart" "rm" "inspect" "ps" "run")
CONTAINER_RE='^mcp-instance-[a-zA-Z0-9]+$'

cmd="${1:-}"; shift || true
case " ${ALLOWED_CMDS[*]} " in
  *" ${cmd} "*) ;;
  *) echo "Denied: cmd not allowed"; exit 1 ;;
esac

# Commands that require a container name
if [[ "${cmd}" != "ps" ]] && [[ "${cmd}" != "run" ]]; then
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
    
    # Find the image name in the arguments (it's typically the first non-flag argument)
    # Skip --name parameter and its value
    for i in "${!args[@]}"; do
      arg="${args[$i]}"
      if [[ ! "$arg" =~ ^- ]] && [[ -n "$arg" ]]; then
        # Check if this argument is not the value of a --name parameter
        if [[ $i -gt 0 ]] && [[ "${args[$((i-1))]}" == "--name" ]]; then
          continue  # Skip this argument as it's the container name
        fi
        image_name="$arg"
        break
      fi
    done
    
    # Validate image name
    if [[ "$image_name" != "maas-mcp-instance" ]]; then
      echo "Denied: only maas-mcp-instance image is allowed"
      exit 1
    fi
    
    exec /usr/bin/docker run "$@"
  } ;;
esac
