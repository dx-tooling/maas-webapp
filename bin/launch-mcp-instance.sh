#!/usr/bin/env bash
set -euo pipefail

# MCP Instance launch script for MCP-as-a-Service
# Creates and launches containerized MCP instances with Traefik routing

# Configuration
MCP_IMAGE_NAME="${MCP_IMAGE_NAME:-maas-mcp-instance}"
MCP_CONTAINER_PREFIX="${MCP_CONTAINER_PREFIX:-mcp-instance}"
MCP_NETWORK="${MCP_NETWORK:-mcp_instances}"
MCP_MEMORY_LIMIT="${MCP_MEMORY_LIMIT:-1g}"
MCP_RESTART_POLICY="${MCP_RESTART_POLICY:-unless-stopped}"

# Domain and Routing Configuration
DOMAIN_NAME="${DOMAIN_NAME:-mcp-as-a-service.com}"
MCP_SUBDOMAIN_PREFIX="${MCP_SUBDOMAIN_PREFIX:-mcp}"
VNC_SUBDOMAIN_PREFIX="${VNC_SUBDOMAIN_PREFIX:-vnc}"
TRAEFIK_ENTRYPOINT="${TRAEFIK_ENTRYPOINT:-websecure}"

# MCP Instance Configuration
INSTANCE_ID="${INSTANCE_ID:-test}"
SCREEN_WIDTH="${SCREEN_WIDTH:-1920}"
SCREEN_HEIGHT="${SCREEN_HEIGHT:-1080}"
COLOR_DEPTH="${COLOR_DEPTH:-24}"
VNC_PASSWORD="${VNC_PASSWORD:-testpass123}"

# Port Configuration
MCP_PORT="${MCP_PORT:-8080}"
VNC_PORT="${VNC_PORT:-5900}"
NOVNC_PORT="${NOVNC_PORT:-6080}"

# Traefik Configuration
TRAEFIK_DASHBOARD_PORT="${TRAEFIK_DASHBOARD_PORT:-8080}"
ENABLE_FORWARDAUTH="${ENABLE_FORWARDAUTH:-false}"
FORWARDAUTH_URL="${FORWARDAUTH_URL:-https://app.${DOMAIN_NAME}/auth/mcp-bearer-check}"

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
    if ! docker network ls | grep -q "${MCP_NETWORK}"; then
        log_info "Creating Docker network: ${MCP_NETWORK}"
        docker network create "${MCP_NETWORK}"
        log_success "Network ${MCP_NETWORK} created"
    else
        log_info "Network ${MCP_NETWORK} already exists"
    fi
}

# Generate container and subdomain names
generate_names() {
    CONTAINER_NAME="${MCP_CONTAINER_PREFIX}-${INSTANCE_ID}"
    MCP_ROUTER_NAME="mcp-${INSTANCE_ID}"
    VNC_ROUTER_NAME="vnc-${INSTANCE_ID}"
    MCP_SUBDOMAIN="${MCP_SUBDOMAIN_PREFIX}-${INSTANCE_ID}.${DOMAIN_NAME}"
    VNC_SUBDOMAIN="${VNC_SUBDOMAIN_PREFIX}-${INSTANCE_ID}.${DOMAIN_NAME}"
}

# Stop and remove existing container
cleanup_existing() {
    if docker ps -a --format "table {{.Names}}" | grep -q "^${CONTAINER_NAME}$"; then
        log_info "Stopping existing container: ${CONTAINER_NAME}"
        docker stop "${CONTAINER_NAME}" || true
        docker rm "${CONTAINER_NAME}" || true
        log_success "Existing container cleaned up"
    fi
}

# Launch MCP instance container
launch_mcp_instance() {
    log_info "Launching MCP instance container: ${CONTAINER_NAME}"
    
    local docker_args=(
        --name "${CONTAINER_NAME}"
        --network "${MCP_NETWORK}"
        --restart "${MCP_RESTART_POLICY}"
        --memory="${MCP_MEMORY_LIMIT}"
        -e "INSTANCE_ID=${INSTANCE_ID}"
        -e "SCREEN_WIDTH=${SCREEN_WIDTH}"
        -e "SCREEN_HEIGHT=${SCREEN_HEIGHT}"
        -e "COLOR_DEPTH=${COLOR_DEPTH}"
        -e "VNC_PASSWORD=${VNC_PASSWORD}"
        --label "traefik.enable=true"
    )
    
    # MCP routing labels
    docker_args+=(
        --label "traefik.http.routers.${MCP_ROUTER_NAME}.rule=Host(\`${MCP_SUBDOMAIN}\`)"
        --label "traefik.http.routers.${MCP_ROUTER_NAME}.entrypoints=${TRAEFIK_ENTRYPOINT}"
        --label "traefik.http.routers.${MCP_ROUTER_NAME}.tls=true"
        --label "traefik.http.services.${MCP_ROUTER_NAME}.loadbalancer.server.port=${MCP_PORT}"
    )
    
    # VNC routing labels
    docker_args+=(
        --label "traefik.http.routers.${VNC_ROUTER_NAME}.rule=Host(\`${VNC_SUBDOMAIN}\`)"
        --label "traefik.http.routers.${VNC_ROUTER_NAME}.entrypoints=${TRAEFIK_ENTRYPOINT}"
        --label "traefik.http.routers.${VNC_ROUTER_NAME}.tls=true"
        --label "traefik.http.services.${VNC_ROUTER_NAME}.loadbalancer.server.port=${NOVNC_PORT}"
    )
    
    # Add ForwardAuth middleware if enabled
    if [[ "${ENABLE_FORWARDAUTH}" == "true" ]]; then
        docker_args+=(
            --label "traefik.http.middlewares.${MCP_ROUTER_NAME}-auth.forwardauth.address=${FORWARDAUTH_URL}"
            --label "traefik.http.routers.${MCP_ROUTER_NAME}.middlewares=${MCP_ROUTER_NAME}-auth"
        )
        log_info "ForwardAuth middleware enabled for MCP endpoint"
    else
        log_warning "ForwardAuth middleware disabled - MCP endpoint will be publicly accessible"
    fi
    
    # Print the full docker command for debugging
    log_info "Executing docker command:"
    echo "  docker run -d \\"
    for arg in "${docker_args[@]}"; do
        echo "    '${arg}' \\"
    done
    echo "    '${MCP_IMAGE_NAME}'"
    echo
    
    docker run -d "${docker_args[@]}" "${MCP_IMAGE_NAME}"
    
    log_success "Container launched successfully"
}

# Display status and information
show_status() {
    log_info "Container status:"
    docker ps --filter "name=${CONTAINER_NAME}" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
    
    echo
    log_info "Network information:"
    docker network inspect "${MCP_NETWORK}" --format "{{.Name}}: {{.IPAM.Config}}"
    
    echo
    log_info "Access endpoints:"
    echo "  - MCP: https://${MCP_SUBDOMAIN}/"
    echo "  - VNC (noVNC): https://${VNC_SUBDOMAIN}/"
    echo "  - Health check: https://${MCP_SUBDOMAIN}/health"
    
    echo
    log_info "Container logs:"
    echo "  docker logs -f ${CONTAINER_NAME}"
    
    echo
    log_info "Stop container:"
    echo "  docker stop ${CONTAINER_NAME}"
    
    echo
    log_info "Debug commands:"
    echo "  # Check container status:"
    echo "  docker ps --filter name=${CONTAINER_NAME}"
    echo "  # Check container logs:"
    echo "  docker logs ${CONTAINER_NAME}"
    echo "  # Check Traefik routing:"
    echo "  curl -s http://localhost:${TRAEFIK_DASHBOARD_PORT}/api/http/routers | jq '.[] | select(.name | contains(\"${INSTANCE_ID}\"))'"
    echo "  # Test MCP endpoint:"
    echo "  curl -k -v https://${MCP_SUBDOMAIN}/"
    echo "  # Test VNC endpoint:"
    echo "  curl -k -v https://${VNC_SUBDOMAIN}/"
    echo "  # Check container health:"
    echo "  docker exec ${CONTAINER_NAME} curl -s http://localhost:${MCP_PORT}/health || echo 'MCP not ready'"
    echo "  docker exec ${CONTAINER_NAME} curl -s http://localhost:${NOVNC_PORT}/ || echo 'noVNC not ready'"
}

# Main execution
main() {
    echo "🚀 Launching MCP Instance Container"
    echo "====================================="
    
    # Check prerequisites
    check_docker
    
    # Generate names based on configuration
    generate_names
    
    log_info "Configuration:"
    echo "  - Instance ID: ${INSTANCE_ID}"
    echo "  - Container Name: ${CONTAINER_NAME}"
    echo "  - Network: ${MCP_NETWORK}"
    echo "  - Memory Limit: ${MCP_MEMORY_LIMIT}"
    echo "  - MCP Subdomain: ${MCP_SUBDOMAIN}"
    echo "  - VNC Subdomain: ${VNC_SUBDOMAIN}"
    echo "  - ForwardAuth: ${ENABLE_FORWARDAUTH}"
    
    # Setup and launch
    create_network
    cleanup_existing
    launch_mcp_instance
    
    # Give container a moment to start
    sleep 3
    
    show_status
    log_success "MCP instance deployment completed successfully!"
}

# Handle script arguments
case "${1:-}" in
    --help|-h)
        echo "Usage: $0 [--help] [--instance-id ID] [--forwardauth]"
        echo ""
        echo "Options:"
        echo "  --help, -h          Show this help message"
        echo "  --instance-id ID    Set instance ID (default: test)"
        echo "  --forwardauth       Enable ForwardAuth middleware for MCP endpoint"
        echo ""
        echo "Environment variables:"
        echo "  # Container Configuration"
        echo "  MCP_IMAGE_NAME          Docker image (default: maas-mcp-instance)"
        echo "  MCP_CONTAINER_PREFIX    Container name prefix (default: mcp-instance)"
        echo "  MCP_NETWORK             Docker network (default: mcp_instances)"
        echo "  MCP_MEMORY_LIMIT        Memory limit (default: 1g)"
        echo "  MCP_RESTART_POLICY      Restart policy (default: unless-stopped)"
        echo ""
        echo "  # Domain Configuration"
        echo "  DOMAIN_NAME             Main domain (default: mcp-as-a-service.com)"
        echo "  MCP_SUBDOMAIN_PREFIX    MCP subdomain prefix (default: mcp)"
        echo "  VNC_SUBDOMAIN_PREFIX    VNC subdomain prefix (default: vnc)"
        echo ""
        echo "  # Instance Configuration"
        echo "  INSTANCE_ID             Instance identifier (default: test)"
        echo "  SCREEN_WIDTH            Screen width (default: 1920)"
        echo "  SCREEN_HEIGHT           Screen height (default: 1080)"
        echo "  COLOR_DEPTH             Color depth (default: 24)"
        echo "  VNC_PASSWORD            VNC password (default: testpass123)"
        echo ""
        echo "  # Port Configuration"
        echo "  MCP_PORT                MCP server port (default: 8080)"
        echo "  VNC_PORT                VNC server port (default: 5900)"
        echo "  NOVNC_PORT              noVNC web port (default: 6080)"
        echo ""
        echo "  # Traefik Configuration"
        echo "  TRAEFIK_ENTRYPOINT      Traefik entrypoint (default: websecure)"
        echo "  ENABLE_FORWARDAUTH      Enable ForwardAuth (default: false)"
        echo "  FORWARDAUTH_URL         ForwardAuth endpoint URL"
        echo ""
        echo "Examples:"
        echo "  $0                                    # Launch with defaults"
        echo "  $0 --instance-id demo                 # Custom instance ID"
        echo "  $0 --forwardauth                      # Enable authentication"
        echo ""
        echo "  # Custom domain configuration"
        echo "  DOMAIN_NAME=example.com INSTANCE_ID=prod $0"
        echo ""
        echo "  # Custom container configuration"
        echo "  MCP_MEMORY_LIMIT=2g SCREEN_WIDTH=2560 $0"
        echo ""
        echo "  # Development with custom image"
        echo "  MCP_IMAGE_NAME=my-mcp-image:latest INSTANCE_ID=dev $0"
        exit 0
        ;;
    --instance-id)
        if [[ -n "${2:-}" ]]; then
            INSTANCE_ID="$2"
            shift 2
        else
            log_error "Instance ID required after --instance-id"
            exit 1
        fi
        ;;
    --forwardauth)
        ENABLE_FORWARDAUTH="true"
        shift
        ;;
    "")
        # Use defaults
        ;;
    *)
        log_error "Unknown option: $1"
        echo "Use --help for usage information"
        exit 1
        ;;
esac

# Run main function
main "$@"
