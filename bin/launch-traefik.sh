#!/usr/bin/env bash
set -euo pipefail

# Traefik launch script for MCP-as-a-Service
# Supports both development and production environments

# Configuration
TRAEFIK_VERSION="${TRAEFIK_VERSION:-v3.5}"
TRAEFIK_CONTAINER_NAME="${TRAEFIK_CONTAINER_NAME:-traefik-mcp}"
TRAEFIK_NETWORK="${TRAEFIK_NETWORK:-mcp_instances}"
TRAEFIK_DASHBOARD_PORT="${TRAEFIK_DASHBOARD_PORT:-8080}"
TRAEFIK_HTTP_PORT="${TRAEFIK_HTTP_PORT:-80}"
TRAEFIK_HTTPS_PORT="${TRAEFIK_HTTPS_PORT:-443}"

# Domain and Certificate Configuration
DOMAIN_NAME="${DOMAIN_NAME:-mcp-as-a-service.com}"
APP_SUBDOMAIN="${APP_SUBDOMAIN:-app}"
MCP_SUBDOMAIN_PATTERN="${MCP_SUBDOMAIN_PATTERN:-mcp-*}"
VNC_SUBDOMAIN_PATTERN="${VNC_SUBDOMAIN_PATTERN:-vnc-*}"
LETSENCRYPT_PATH="${LETSENCRYPT_PATH:-/etc/letsencrypt}"
TRAEFIK_LOG_PATH="${TRAEFIK_LOG_PATH:-/var/log/traefik}"
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
    if ! docker network ls | grep -q "${TRAEFIK_NETWORK}"; then
        log_info "Creating Docker network: ${TRAEFIK_NETWORK}"
        docker network create "${TRAEFIK_NETWORK}"
        log_success "Network ${TRAEFIK_NETWORK} created"
    else
        log_info "Network ${TRAEFIK_NETWORK} already exists"
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

# Verify certificate files exist (production only)
verify_certificates() {
    if [[ "${ENVIRONMENT}" == "production" ]]; then
        local cert_file="${LETSENCRYPT_PATH}/live/${DOMAIN_NAME}/fullchain.pem"
        local key_file="${LETSENCRYPT_PATH}/live/${DOMAIN_NAME}/privkey.pem"
        
        if [[ ! -f "${cert_file}" ]]; then
            log_error "Certificate file not found: ${cert_file}"
            log_error "Expected fullchain.pem (certificate + intermediate chain)"
            exit 1
        fi
        
        if [[ ! -f "${key_file}" ]]; then
            log_error "Private key file not found: ${key_file}"
            exit 1
        fi
        
        if [[ ! -r "${cert_file}" ]]; then
            log_error "Certificate file not readable: ${cert_file}"
            exit 1
        fi
        
        if [[ ! -r "${key_file}" ]]; then
            log_error "Private key file not readable: ${key_file}"
            exit 1
        fi
        
        # Additional verification: check certificate content
        log_info "Certificate file details:"
        echo "  - Certificate: $(ls -la "${cert_file}")"
        echo "  - Private key: $(ls -la "${key_file}")"
        
        # Check if certificate is valid and contains full chain
        if openssl x509 -in "${cert_file}" -text -noout >/dev/null 2>&1; then
            log_success "Certificate file is valid PEM format"
            
            # Check if it's a wildcard certificate and verify domain coverage
            local cert_subject=$(openssl x509 -in "${cert_file}" -subject -noout 2>/dev/null || echo "")
            local cert_sans=$(openssl x509 -in "${cert_file}" -text -noout 2>/dev/null | grep -A 1 "Subject Alternative Name:" || echo "")
            
            log_info "Certificate details:"
            echo "  - Subject: ${cert_subject}"
            echo "  - SANs: ${cert_sans}"
            
            # Verify it covers our domains
            if openssl x509 -in "${cert_file}" -text -noout 2>/dev/null | grep -q "*.${DOMAIN_NAME}\|${APP_SUBDOMAIN}.${DOMAIN_NAME}"; then
                log_success "Certificate covers required domains"
            else
                log_warning "Certificate may not cover ${APP_SUBDOMAIN}.${DOMAIN_NAME} - check Subject Alternative Names"
            fi
        else
            log_warning "Certificate file may not be valid PEM format"
        fi
        
        log_success "Certificate files verified and readable"
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
        
        # Check if certificates section exists in dynamic config
        if grep -q "certificates:" "${dynamic_config_file}"; then
            log_success "Certificates section found in dynamic configuration"
        else
            log_error "Certificates section missing from dynamic configuration"
            return 1
        fi
        
        # Check if stores section exists in dynamic config
        if grep -q "stores:" "${dynamic_config_file}"; then
            log_success "Stores section found in dynamic configuration"
        else
            log_error "Stores section missing from dynamic configuration"
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

# Providers
providers:
  docker:
    endpoint: "unix:///var/run/docker.sock"
    exposedByDefault: false
    network: "${TRAEFIK_NETWORK}"
    useBindPortIP: false
    watch: true
    constraints: "Label(\`traefik.enable\`, \`true\`)"
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

# TLS configuration with existing certificates
# This configuration tells Traefik to use the existing wildcard certificates
# from the host system as the default certificate for all TLS connections
tls:
  options:
    default:
      minVersion: VersionTLS12
      sniStrict: false
  certificates:
    - certFile: /etc/traefik/letsencrypt/live/${DOMAIN_NAME}/fullchain.pem
      keyFile: /etc/traefik/letsencrypt/live/${DOMAIN_NAME}/privkey.pem
  stores:
    default:
      defaultCertificate:
        certFile: /etc/traefik/letsencrypt/live/${DOMAIN_NAME}/fullchain.pem
        keyFile: /etc/traefik/letsencrypt/live/${DOMAIN_NAME}/privkey.pem
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
    
    local docker_args=(
        --name "${TRAEFIK_CONTAINER_NAME}"
        --network "${TRAEFIK_NETWORK}"
        --restart unless-stopped
        --health-cmd="curl -f http://localhost:${TRAEFIK_DASHBOARD_PORT}/api/http/routers || exit 1"
        --health-interval=30s
        --health-timeout=10s
        --health-retries=3
        -p "${TRAEFIK_HTTP_PORT}:${TRAEFIK_HTTP_PORT}"
        -p "${TRAEFIK_HTTPS_PORT}:${TRAEFIK_HTTPS_PORT}"
        -p "${TRAEFIK_DASHBOARD_PORT}:${TRAEFIK_DASHBOARD_PORT}"
        -v /var/run/docker.sock:/var/run/docker.sock:ro
        -v "${static_config_file}:/etc/traefik/traefik.yml:ro"
        -v "${dynamic_config_file}:/etc/traefik/dynamic.yml:ro"
    )
    
    if [[ "${ENVIRONMENT}" == "production" ]]; then
        # Production: mount existing wildcard certificates
        # Mount the parent directory to access both the live and archive directories
        docker_args+=(
            -v "${LETSENCRYPT_PATH}:/etc/traefik/letsencrypt:ro"
            -v "${TRAEFIK_LOG_PATH}:/var/log/traefik"
        )
    fi
    
    # Add host access for production (to reach native nginx on port 8080)
    if [[ "${ENVIRONMENT}" == "production" ]]; then
        docker_args+=(--add-host="${DOCKER_HOST_ALIAS}:host-gateway")
    fi
    
    docker run -d "${docker_args[@]}" \
        -e "TRAEFIK_LOG_LEVEL=DEBUG" \
        -e "TRAEFIK_ACCESSLOG=true" \
        -e "TRAEFIK_METRICS_PROMETHEUS=true" \
        -e "TRAEFIK_TLS_OPTIONS_DEFAULT_MINVERSION=VersionTLS12" \
        "traefik:${TRAEFIK_VERSION}"
    
    log_success "Traefik container launched successfully"
}

# Wait for Traefik to be ready
wait_for_traefik() {
    log_info "Waiting for Traefik to be ready..."
    
    local max_attempts=30
    local attempt=1
    
    while [[ ${attempt} -le ${max_attempts} ]]; do
        if curl -s "http://localhost:${TRAEFIK_DASHBOARD_PORT}/api/http/routers" >/dev/null 2>&1; then
            log_success "Traefik is ready and responding"
            return 0
        fi
        
        echo -n "."
        sleep 2
        ((attempt++))
    done
    
    log_error "Traefik failed to become ready within ${max_attempts} attempts"
    return 1
}

# Display status and information
show_status() {
    log_info "Traefik container status:"
    docker ps --filter "name=${TRAEFIK_CONTAINER_NAME}" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
    
    echo
    log_info "Network information:"
    docker network inspect "${TRAEFIK_NETWORK}" --format "{{.Name}}: {{.IPAM.Config}}"
    
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
        echo "  - Using existing wildcard certificates from ${LETSENCRYPT_PATH}/live/${DOMAIN_NAME}/"
        echo "  - Certificate files:"
        echo "    - Cert: /etc/traefik/letsencrypt/live/${DOMAIN_NAME}/fullchain.pem"
        echo "    - Key: /etc/traefik/letsencrypt/live/${DOMAIN_NAME}/privkey.pem"
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
        echo "  # Check certificate files:"
        echo "  ls -la ${LETSENCRYPT_PATH}/live/${DOMAIN_NAME}/"
        echo "  # Check certificate content:"
        echo "  openssl x509 -in ${LETSENCRYPT_PATH}/live/${DOMAIN_NAME}/fullchain.pem -text -noout | grep -A 5 'Subject:'"
        echo "  # Check Traefik logs:"
        echo "  tail -f ${TRAEFIK_LOG_PATH}/traefik.log"
        echo "  # Test HTTPS endpoint:"
        echo "  curl -v https://${APP_SUBDOMAIN}.${DOMAIN_NAME}/"
        echo "  # Test HTTPS endpoint ignoring certificate verification (to check if cert is served):"
        echo "  curl -k -v https://${APP_SUBDOMAIN}.${DOMAIN_NAME}/"
        echo "  # Check certificate chain with openssl:"
        echo "  echo | openssl s_client -servername ${APP_SUBDOMAIN}.${DOMAIN_NAME} -connect ${APP_SUBDOMAIN}.${DOMAIN_NAME}:443 2>/dev/null | openssl x509 -noout -issuer -subject"
        echo "  # Verify certificate chain:"
        echo "  echo | openssl s_client -servername ${APP_SUBDOMAIN}.${DOMAIN_NAME} -connect ${APP_SUBDOMAIN}.${DOMAIN_NAME}:443 -verify_return_error"
        echo "  # Check container health:"
        echo "  docker inspect ${TRAEFIK_CONTAINER_NAME} | grep -A 10 Health"
        echo "  # Check Traefik API for TLS info:"
        echo "  curl -s http://localhost:${TRAEFIK_DASHBOARD_PORT}/api/http/tls/certificates | jq"
        echo "  # Check Traefik API for stores info:"
        echo "  curl -s http://localhost:${TRAEFIK_DASHBOARD_PORT}/api/http/tls/stores | jq"
        echo "  # Verify certificate files in container:"
        echo "  docker exec ${TRAEFIK_CONTAINER_NAME} ls -la /etc/traefik/letsencrypt/live/${DOMAIN_NAME}/"
        echo "  # Check certificate validity inside container:"
        echo "  docker exec ${TRAEFIK_CONTAINER_NAME} openssl x509 -in /etc/traefik/letsencrypt/live/${DOMAIN_NAME}/fullchain.pem -noout -subject -issuer -dates"
        echo "  # Check static configuration in container:"
        echo "  docker exec ${TRAEFIK_CONTAINER_NAME} cat /etc/traefik/traefik.yml"
        echo "  # Check dynamic configuration in container:"
        echo "  docker exec ${TRAEFIK_CONTAINER_NAME} cat /etc/traefik/dynamic.yml"
        echo "  # Test certificate loading in Traefik dynamic config:"
        echo "  docker exec ${TRAEFIK_CONTAINER_NAME} cat /etc/traefik/dynamic.yml | grep -A 10 certificates"
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
                DASHBOARD_INSECURE="false"
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
    create_log_directory # Call the new function here
    verify_certificates # Call the new function here
    
    # Generate and use configuration
    local config_files
    config_files=$(generate_config)
    
    # Verify configuration
    verify_config "${config_files}"
    
    # Launch container
    launch_traefik "${config_files}"
    
    # Wait for readiness
    if wait_for_traefik; then
        show_status
        log_success "Traefik deployment completed successfully!"
    else
        log_error "Traefik deployment failed"
        exit 1
    fi
    
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
        echo "  TRAEFIK_NETWORK         Docker network (default: mcp_instances)"
        echo "  TRAEFIK_DASHBOARD_PORT  Dashboard port (default: 8080)"
        echo "  TRAEFIK_HTTP_PORT       HTTP port (default: 80)"
        echo "  TRAEFIK_HTTPS_PORT      HTTPS port (default: 443)"
        echo ""
        echo "  # Domain & Certificate Configuration"
        echo "  DOMAIN_NAME             Main domain (default: mcp-as-a-service.com)"
        echo "  APP_SUBDOMAIN           App subdomain (default: app)"
        echo "  MCP_SUBDOMAIN_PATTERN   MCP subdomain pattern (default: mcp-*)"
        echo "  VNC_SUBDOMAIN_PATTERN   VNC subdomain pattern (default: vnc-*)"
        echo "  LETSENCRYPT_PATH        Let's Encrypt path (default: /etc/letsencrypt)"
        echo "  TRAEFIK_LOG_PATH        Log directory (default: /var/log/traefik)"
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
        echo "  # Custom certificate path"
        echo "  LETSENCRYPT_PATH=/custom/certs DOMAIN_NAME=example.com $0 --prod"
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
