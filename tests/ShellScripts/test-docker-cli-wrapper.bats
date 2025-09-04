#!/usr/bin/env bats

setup() {
  WRAPPER="$(pwd)/bin/docker-cli-wrapper.sh"
  [[ -x "$WRAPPER" ]]
}

@test "denies unknown command" {
  run "$WRAPPER" foo
  [ "$status" -eq 1 ]
  [[ "$output" == Denied:* ]]
}

@test "denies bad container name on start" {
  run "$WRAPPER" start bad-name
  [ "$status" -eq 1 ]
  [[ "$output" == Denied:* ]]
}

@test "allows ps with validation" {
  run env MAAS_WRAPPER_VALIDATE_ONLY=1 DOCKER_BIN=/bin/docker "$WRAPPER" ps
  [ "$status" -eq 0 ]
  [[ "$output" == *"/bin/docker ps"* ]]
  [[ "$output" == *"--filter"* ]]
  [[ "$output" == *"mcp-instance-"* ]]
  [[ "$output" == *"--format"* ]]
}

@test "allows inspect with flags and good name (validation)" {
  run env MAAS_WRAPPER_VALIDATE_ONLY=1 DOCKER_BIN=/bin/docker "$WRAPPER" inspect --format '{{.State.Status}}' mcp-instance-abc123
  [ "$status" -eq 0 ]
  [[ "$output" == *"/bin/docker inspect"* ]]
  [[ "$output" == *"--format"* ]]
  [[ "$output" == *"mcp-instance-abc123"* ]]
}

@test "denies exec missing/invalid name" {
  run "$WRAPPER" exec --env FOO=bar
  [ "$status" -eq 1 ]
  [[ "$output" == Denied:* ]]
}

@test "allows exec with good name (validation)" {
  run env MAAS_WRAPPER_VALIDATE_ONLY=1 DOCKER_BIN=/bin/docker "$WRAPPER" exec mcp-instance-abc123 echo ok
  [ "$status" -eq 0 ]
  [[ "$output" == "/bin/docker exec mcp-instance-abc123 echo ok" ]]
}

@test "denies run with wrong image" {
  run "$WRAPPER" run --name mcp-instance-abc123 busybox
  [ "$status" -eq 1 ]
  [[ "$output" == Denied:* ]]
}

@test "allows run with correct image, name, and flags (validation)" {
  run env MAAS_WRAPPER_VALIDATE_ONLY=1 DOCKER_BIN=/bin/docker "$WRAPPER" run --name mcp-instance-abc123 -e FOO=bar maas-mcp-instance
  [ "$status" -eq 0 ]
  [[ "$output" == "/bin/docker run --name mcp-instance-abc123 -e FOO=bar maas-mcp-instance" ]]
}

@test "allows run with prefixed image name (validation)" {
  run env MAAS_WRAPPER_VALIDATE_ONLY=1 DOCKER_BIN=/bin/docker "$WRAPPER" run --name mcp-instance-xyz987 -e BAR=baz maas-mcp-instance-playwright-v1
  [ "$status" -eq 0 ]
  [[ "$output" == "/bin/docker run --name mcp-instance-xyz987 -e BAR=baz maas-mcp-instance-playwright-v1" ]]
}


