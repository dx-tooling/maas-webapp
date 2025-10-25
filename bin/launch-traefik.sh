#!/usr/bin/env bash
set -euo pipefail

# Traefik launch script for MCP-as-a-Service
# Supports both development and production environments

# Configuration
TRAEFIK_VERSION="${TRAEFIK_VERSION:-v3.5.3}"
TRAEFIK_CONTAINER_NAME="${TRAEFIK_CONTAINER_NAME:-mcp-as-a-service-traefik}"
TRAEFIK_NETWORK_SELF="${TRAEFIK_NETWORK_SELF:-mcp_instances}"
TRAEFIK_NETWORK_OUTERMOST_ROUTER="${TRAEFIK_NETWORK_OUTERMOST_ROUTER:-outermost_router}"
TRAEFIK_DASHBOARD_PORT="${TRAEFIK_DASHBOARD_PORT:-8080}"
TRAEFIK_HTTP_PORT="${TRAEFIK_HTTP_PORT:-80}"
TRAEFIK_HTTPS_PORT="${TRAEFIK_HTTPS_PORT:-443}"

# Domain Configuration
DOMAIN_NAME="${DOMAIN_NAME:-mcp-as-a-service.com}"
APP_SUBDOMAIN="${APP_SUBDOMAIN:-app}"
MCP_SUBDOMAIN_PATTERN="${MCP_SUBDOMAIN_PATTERN:-mcp-*}"
VNC_SUBDOMAIN_PATTERN="${VNC_SUBDOMAIN_PATTERN:-vnc-*}"
TRAEFIK_LOG_PATH="${TRAEFIK_LOG_PATH:-/var/log/mcp-as-a-service-traefik}"
TRAEFIK_USER_ID="${TRAEFIK_USER_ID:-1000}"
TRAEFIK_GROUP_ID="${TRAEFIK_GROUP_ID:-1000}"

# Docker Configuration
DOCKER_HOST_ALIAS="${DOCKER_HOST_ALIAS:-host.docker.internal}"

# Environment detection (will be set by argument parsing)
ENVIRONMENT="production"
DASHBOARD_INSECURE="false"
TLS_CERT_RESOLVER=""
ENTRYPOINTS="web,websecure"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Helper functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Docker is running
check_docker() {
    if ! docker info >/dev/null 2>&1; then
        log_error "Docker is not running or not accessible"
        exit 1
    fi
    log_success "Docker is running"
}

# Create Docker network if it doesn't exist
create_network() {
    if ! docker network ls | grep -q "${TRAEFIK_NETWORK_SELF}"; then
        log_info "Creating Docker network: ${TRAEFIK_NETWORK_SELF}"
        docker network create "${TRAEFIK_NETWORK_SELF}"
        log_success "Network ${TRAEFIK_NETWORK_SELF} created"
    else
        log_info "Network ${TRAEFIK_NETWORK_SELF} already exists"
    fi
}

# Create log directory if it doesn't exist
create_log_directory() {
    if [[ "${ENVIRONMENT}" == "production" ]]; then
        if [[ ! -d "${TRAEFIK_LOG_PATH}" ]]; then
            log_info "Creating log directory: ${TRAEFIK_LOG_PATH}"
            sudo mkdir -p "${TRAEFIK_LOG_PATH}"
            sudo chown "${TRAEFIK_USER_ID}:${TRAEFIK_GROUP_ID}" "${TRAEFIK_LOG_PATH}"
            log_success "Log directory created"
        else
            log_info "Log directory already exists"
        fi
    fi
}

# Verify generated configuration
verify_config() {
    local config_files="$1"
    local static_config_file=$(echo "${config_files}" | cut -d' ' -f1)
    local dynamic_config_file=$(echo "${config_files}" | cut -d' ' -f2)

    if [[ "${ENVIRONMENT}" == "production" ]]; then
        log_info "Verifying generated configuration..."

        # Check static configuration
        log_info "Checking static configuration: ${static_config_file}"
        if [[ ! -f "${static_config_file}" ]]; then
            log_error "Static configuration file not found: ${static_config_file}"
            return 1
        fi

        # Check if file provider exists in static config
        if grep -q "file:" "${static_config_file}"; then
            log_success "File provider found in static configuration"
        else
            log_error "File provider missing from static configuration"
            return 1
        fi

        # Check dynamic configuration
        log_info "Checking dynamic configuration: ${dynamic_config_file}"
        if [[ ! -f "${dynamic_config_file}" ]]; then
            log_error "Dynamic configuration file not found: ${dynamic_config_file}"
            return 1
        fi

        # Check if TLS section exists in dynamic config
        if grep -q "tls:" "${dynamic_config_file}"; then
            log_success "TLS section found in dynamic configuration"
        else
            log_error "TLS section missing from dynamic configuration"
            return 1
        fi

        log_info "Configuration verification completed successfully"
    fi
}

# Stop and remove existing Traefik container
cleanup_existing() {
    if docker ps -a --format "table {{.Names}}" | grep -q "^${TRAEFIK_CONTAINER_NAME}$"; then
        log_info "Stopping existing Traefik container: ${TRAEFIK_CONTAINER_NAME}"
        docker stop "${TRAEFIK_CONTAINER_NAME}" || true
        docker rm "${TRAEFIK_CONTAINER_NAME}" || true
        log_success "Existing container cleaned up"
    fi
}

# Generate Traefik configuration (split into static and dynamic)
generate_config() {
    local static_config_file="/tmp/traefik-${TRAEFIK_CONTAINER_NAME}.yml"
    local dynamic_config_file="/tmp/traefik-${TRAEFIK_CONTAINER_NAME}-dynamic.yml"

    # Generate static configuration (traefik.yml)
    cat > "${static_config_file}" << EOF
# Traefik configuration for MCP-as-a-Service
# Generated by launch-traefik.sh

# API and Dashboard
api:
  dashboard: true
  insecure: ${DASHBOARD_INSECURE}
  # In production mode, API is accessible via the traefik entrypoint
  # In development mode, API is insecure and accessible directly

ping: {}

# Global options
global:
  checkNewVersion: false
  sendAnonymousUsage: false

# Entrypoints
entryPoints:
  web:
    address: ":${TRAEFIK_HTTP_PORT}"
  websecure:
    address: ":${TRAEFIK_HTTPS_PORT}"
  traefik:
    address: ":${TRAEFIK_DASHBOARD_PORT}"

# Providers
providers:
  docker:
    endpoint: "unix:///var/run/docker.sock"
    exposedByDefault: false
    network: "${TRAEFIK_NETWORK_SELF}"
    useBindPortIP: false
    watch: true
    constraints: "Label(\`is_mcp_instance\`, \`true\`)"
  file:
    filename: "/etc/traefik/dynamic.yml"
    watch: true

# Logging
log:
  level: INFO
  filePath: "${TRAEFIK_LOG_PATH}/traefik.log"
  format: json

# Access logs
accessLog:
  filePath: "${TRAEFIK_LOG_PATH}/access.log"
  format: json

# Metrics (optional, for monitoring)
metrics:
  prometheus: {}
EOF


    # Generate dynamic configuration (dynamic.yml)
    if [[ "${ENVIRONMENT}" == "production" ]]; then
        cat > "${dynamic_config_file}" << EOF
# Dynamic configuration for MCP-as-a-Service (Production)
# Generated by launch-traefik.sh

# Server transport configuration for production
serversTransport:
  insecureSkipVerify: false

# Security headers middleware
http:
  middlewares:
    redirect-to-https:
      redirectScheme:
        scheme: https
        permanent: true
    security-headers:
      headers:
        frameDeny: true
        sslRedirect: true
        browserXssFilter: true
        contentTypeNosniff: true
        forceSTSHeader: true
        stsIncludeSubdomains: true
        stsPreload: true
        stsSeconds: 31536000

  # API and Dashboard routing (required when insecure: false)
  routers:
    api:
      rule: "Host(\`localhost\`) || Host(\`127.0.0.1\`)"
      service: api@internal
      entryPoints:
        - traefik
      tls: false

    # Route apex domain HTTPS to host nginx on the Linux host
    apex-http:
      rule: "Host(\`${DOMAIN_NAME}\`)"
      entryPoints:
        - web
      service: host-nginx
      tls: false

    # Route app subdomain HTTPS to host nginx on the Linux host
    app-http:
      rule: "Host(\`${APP_SUBDOMAIN}.${DOMAIN_NAME}\`)"
      entryPoints:
        - web
      service: host-nginx
      tls: false

  services:
    host-nginx:
      loadBalancer:
        passHostHeader: true
        servers:
          - url: "http://${DOCKER_HOST_ALIAS}:8090"

EOF
    else
        cat > "${dynamic_config_file}" << EOF
# Dynamic configuration for MCP-as-a-Service (Development)
# Generated by launch-traefik.sh

# TLS configuration (development mode)
tls:
  options:
    default:
      minVersion: VersionTLS12
EOF
    fi

    # Return both file paths (space-separated)
    echo "${static_config_file} ${dynamic_config_file}"
}

# Launch Traefik container
launch_traefik() {
    local config_files="$1"
    local static_config_file=$(echo "${config_files}" | cut -d' ' -f1)
    local dynamic_config_file=$(echo "${config_files}" | cut -d' ' -f2)

    log_info "Launching Traefik container: ${TRAEFIK_CONTAINER_NAME}"
    log_info "Static config: ${static_config_file}"
    log_info "Dynamic config: ${dynamic_config_file}"

    local rule="Host(\`mcp-as-a-service.com\`) || HostRegexp(\`^.+\.mcp-as-a-service\.com$\`)"

    local docker_args=(
        --name "${TRAEFIK_CONTAINER_NAME}"
        --network "${TRAEFIK_NETWORK_SELF}"
        --network "${TRAEFIK_NETWORK_OUTERMOST_ROUTER}"
        --restart unless-stopped
        --health-cmd="traefik healthcheck --ping --ping.entrypoint=web"
        --health-interval=2s
        --health-timeout=10s
        --health-retries=3
        -v /var/run/docker.sock:/var/run/docker.sock:ro
        -v "${static_config_file}:/etc/traefik/traefik.yml:ro"
        -v "${dynamic_config_file}:/etc/traefik/dynamic.yml:ro"
        -v "${TRAEFIK_LOG_PATH}:/var/log/traefik"
        -e "TRAEFIK_LOG_LEVEL=DEBUG"
        -e "TRAEFIK_ACCESSLOG=true"
        -e "TRAEFIK_METRICS_PROMETHEUS=true"
        --label outermost_router.enable=true
        --label traefik.enable=true
        --label traefik.docker.network=outermost_router
        --label traefik.http.routers.mcp-as-a-service-traefik.entrypoints=websecure
        --label traefik.http.routers.mcp-as-a-service-traefik.rule="${rule}"
        --label traefik.http.routers.mcp-as-a-service-traefik.tls=true
        --label traefik.http.routers.mcp-as-a-service-traefik.service=mcp-as-a-service-traefik
        --label traefik.http.services.mcp-as-a-service-traefik.loadbalancer.server.port=80
    )

    # Add host access for production (to reach native nginx on port 8080)
    if [[ "${ENVIRONMENT}" == "production" ]]; then
        docker_args+=(--add-host="${DOCKER_HOST_ALIAS}:host-gateway")
    fi

    docker stop "${TRAEFIK_CONTAINER_NAME}" || true
    docker rm "${TRAEFIK_CONTAINER_NAME}" || true

    docker run -d "${docker_args[@]}" \
        "traefik:${TRAEFIK_VERSION}"

    log_success "Traefik container launched successfully"
}

# Display status and information
show_status() {
    log_info "Traefik container status:"
    docker ps --filter "name=${TRAEFIK_CONTAINER_NAME}" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

    echo
    log_info "Network information:"
    docker network inspect "${TRAEFIK_NETWORK_SELF}" --format "{{.Name}}: {{.IPAM.Config}}"

    echo
    if [[ "${ENVIRONMENT}" == "development" ]]; then
        log_info "Development mode:"
        echo "  - Dashboard: http://localhost:${TRAEFIK_DASHBOARD_PORT}/dashboard/"
        echo "  - HTTP: http://localhost:${TRAEFIK_HTTP_PORT}"
        echo "  - HTTPS: https://localhost:${TRAEFIK_HTTPS_PORT}"
    else
        log_info "Production mode:"
        echo "  - Dashboard: https://localhost:${TRAEFIK_DASHBOARD_PORT}/dashboard/"
        echo "  - HTTP: http://localhost:${TRAEFIK_HTTP_PORT}"
        echo "  - HTTPS: https://localhost:${TRAEFIK_HTTPS_PORT}"
        echo "  - Main app: https://${APP_SUBDOMAIN}.${DOMAIN_NAME}"
        echo "  - MCP instances: https://${MCP_SUBDOMAIN_PATTERN}.${DOMAIN_NAME}"
        echo "  - VNC instances: https://${VNC_SUBDOMAIN_PATTERN}.${DOMAIN_NAME}"
        echo "  - Log files: ${TRAEFIK_LOG_PATH}/"
    fi

    echo
    log_info "Container logs:"
    echo "  docker logs -f ${TRAEFIK_CONTAINER_NAME}"

    echo
    log_info "Stop Traefik:"
    echo "  docker stop ${TRAEFIK_CONTAINER_NAME}"

    if [[ "${ENVIRONMENT}" == "production" ]]; then
        echo
        log_info "Debug commands:"
        echo "  # Check Traefik logs:"
        echo "  tail -f ${TRAEFIK_LOG_PATH}/traefik.log"
        echo "  # Test HTTPS endpoint:"
        echo "  curl -v https://${APP_SUBDOMAIN}.${DOMAIN_NAME}/"
        echo "  # Check container health:"
        echo "  docker inspect ${TRAEFIK_CONTAINER_NAME} | grep -A 10 Health"
        echo "  # Check Traefik API for configuration info:"
        echo "  curl -s http://localhost:${TRAEFIK_DASHBOARD_PORT}/api/overview | jq"
        echo "  # Check raw configuration data:"
        echo "  curl -s http://localhost:${TRAEFIK_DASHBOARD_PORT}/api/rawdata | jq"
        echo "  # Check static configuration in container:"
        echo "  docker exec ${TRAEFIK_CONTAINER_NAME} cat /etc/traefik/traefik.yml"
        echo "  # Check dynamic configuration in container:"
        echo "  docker exec ${TRAEFIK_CONTAINER_NAME} cat /etc/traefik/dynamic.yml"
        echo "  # Check Traefik configuration overview:"
        echo "  curl -s http://localhost:${TRAEFIK_DASHBOARD_PORT}/api/overview | jq"
        echo "  # Check all HTTP routers:"
        echo "  curl -s http://localhost:${TRAEFIK_DASHBOARD_PORT}/api/http/routers | jq"
        echo "  # Check all HTTP services:"
        echo "  curl -s http://localhost:${TRAEFIK_DASHBOARD_PORT}/api/http/services | jq"
        echo "  # Check raw configuration data:"
        echo "  curl -s http://localhost:${TRAEFIK_DASHBOARD_PORT}/api/rawdata | jq"
        echo "  # Check if specific endpoints exist:"
        echo "  curl -s http://localhost:${TRAEFIK_DASHBOARD_PORT}/api/version"
    fi
}

# Main execution
main() {
    # Environment variable override (if set)
    if [[ -n "${ENVIRONMENT:-}" ]]; then
        case "${ENVIRONMENT}" in
            development)
                DASHBOARD_INSECURE="true"
                TLS_CERT_RESOLVER=""
                ENTRYPOINTS="web"
                ;;
            production)
                DASHBOARD_INSECURE="true"
                TLS_CERT_RESOLVER=""
                ENTRYPOINTS="web,websecure"
                ;;
        esac
    fi

    # Display environment mode
    if [[ "${ENVIRONMENT}" == "development" ]]; then
        echo "🚀 Launching Traefik in DEVELOPMENT mode"
    else
        echo "🚀 Launching Traefik in PRODUCTION mode"
    fi

    echo "🔧 Traefik Launcher for MCP-as-a-Service"
    echo "=========================================="

    # Check prerequisites
    check_docker

    # Setup
    create_network
    cleanup_existing
    create_log_directory

    # Generate and use configuration
    local config_files
    config_files=$(generate_config)

    # Verify configuration
    verify_config "${config_files}"

    # Launch container
    launch_traefik "${config_files}"

    # Cleanup temporary config files
    if [[ -n "${config_files:-}" ]]; then
        local static_config_file=$(echo "${config_files}" | cut -d' ' -f1)
        local dynamic_config_file=$(echo "${config_files}" | cut -d' ' -f2)
        rm -f "${static_config_file}" "${dynamic_config_file}"
        log_info "Cleaned up temporary configuration files"
    fi
}

# Handle script arguments
case "${1:-}" in
    --help|-h)
        echo "Usage: $0 [--help|--dev|--prod]"
        echo ""
        echo "Options:"
        echo "  --help, -h    Show this help message"
        echo "  --dev         Launch in development mode (insecure dashboard, no TLS)"
        echo "  --prod        Launch in production mode (secure dashboard, TLS enabled)"
        echo ""
        echo "Environment variables:"
        echo "  # Docker & Traefik Configuration"
        echo "  TRAEFIK_VERSION         Traefik version (default: v3.5)"
        echo "  TRAEFIK_CONTAINER_NAME  Container name (default: traefik-mcp)"
        echo "  TRAEFIK_NETWORK_SELF         Docker network (default: mcp_as_a_service)"
        echo "  TRAEFIK_DASHBOARD_PORT  Dashboard port (default: 8080)"
        echo "  TRAEFIK_HTTP_PORT       HTTP port (default: 80)"
        echo "  TRAEFIK_HTTPS_PORT      HTTPS port (default: 443)"
        echo ""
        echo "  # Domain Configuration"
        echo "  DOMAIN_NAME             Main domain (default: mcp-as-a-service.com)"
        echo "  APP_SUBDOMAIN           App subdomain (default: app)"
        echo "  MCP_SUBDOMAIN_PATTERN   MCP subdomain pattern (default: mcp-*)"
        echo "  VNC_SUBDOMAIN_PATTERN   VNC subdomain pattern (default: vnc-*)"
        echo "  TRAEFIK_LOG_PATH        Log directory (default: /var/log/mcp-as-a-service-traefik)"
        echo "  TRAEFIK_USER_ID         Traefik user ID (default: 1000)"
        echo "  TRAEFIK_GROUP_ID        Traefik group ID (default: 1000)"
        echo ""
        echo "  # Docker Configuration"
        echo "  DOCKER_HOST_ALIAS       Docker host alias (default: host.docker.internal)"
        echo ""
        echo "Examples:"
        echo "  $0 --dev                 # Development mode"
        echo "  $0 --prod                # Production mode"
        echo "  ENVIRONMENT=development $0  # Via environment variable"
        echo ""
        echo "  # Custom domain configuration"
        echo "  DOMAIN_NAME=example.com APP_SUBDOMAIN=web $0 --prod"
        echo ""
        echo "  # Custom logging path"
        echo "  TRAEFIK_LOG_PATH=/custom/logs $0 --prod"
        exit 0
        ;;
    --dev)
        ENVIRONMENT="development"
        DASHBOARD_INSECURE="true"
        TLS_CERT_RESOLVER=""
        ENTRYPOINTS="web"
        ;;
    --prod)
        ENVIRONMENT="production"
        DASHBOARD_INSECURE="false"
        TLS_CERT_RESOLVER=""
        ENTRYPOINTS="web,websecure"
        ;;
    "")
        # Use default (production)
        ;;
    *)
        log_error "Unknown option: $1"
        echo "Use --help for usage information"
        exit 1
        ;;
esac

# Run main function
main "$@"
