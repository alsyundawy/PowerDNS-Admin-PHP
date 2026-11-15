#!/usr/bin/env bash
# ==============================================================================
# PowerDNS-Admin-PHP — Post-Installation & Runtime Health Check Suite
# ==============================================================================
# Verifies:
#   1. PHP-FPM daemon & FastCGI Unix socket availability
#   2. MariaDB database connectivity & table schema integrity
#   3. PowerDNS Authoritative REST API connectivity & version detection
# ==============================================================================

set -Eeuo pipefail

RED='\033[1;31m'
GREEN='\033[1;32m'
YELLOW='\033[1;33m'
BLUE='\033[1;34m'
CYAN='\033[1;36m'
NC='\033[0m'

PASS_COUNT=0
WARN_COUNT=0
FAIL_COUNT=0

log_pass() {
	echo -e "  [${GREEN}PASS${NC}] $1"
	((PASS_COUNT++))
}

log_warn() {
	echo -e "  [${YELLOW}WARN${NC}] $1"
	((WARN_COUNT++))
}

log_fail() {
	echo -e "  [${RED}FAIL${NC}] $1"
	((FAIL_COUNT++))
}

echo -e "${CYAN}================================================================================${NC}"
echo -e "${GREEN} PowerDNS-Admin-PHP v0.4.0 — Production Health Check Suite ${NC}"
echo -e "${CYAN}================================================================================${NC}"

# ------------------------------------------------------------------------------
# 1. PHP-FPM Socket Verification
# ------------------------------------------------------------------------------
echo -e "\n${BLUE}[Check 1/3] Verifying PHP-FPM Service & FastCGI Socket...${NC}"

# Potential socket locations
DEFAULT_SOCKS=(
	"${PHP_FPM_SOCK:-}"
	"/run/php/php-fpm-pda.sock"
	"/run/php/php8.5-fpm-pda.sock"
	"/run/php/php8.4-fpm-pda.sock"
	"/run/php/php8.3-fpm-pda.sock"
	"/run/php/php8.2-fpm-pda.sock"
	"/run/php/php8.1-fpm-pda.sock"
	"/var/run/php/php-fpm-pda.sock"
)

FOUND_SOCK=""
for sock in "${DEFAULT_SOCKS[@]}"; do
	if [[ -n ${sock} && -S ${sock} ]]; then
		FOUND_SOCK="${sock}"
		break
	fi
done

if [[ -n ${FOUND_SOCK} ]]; then
	log_pass "PHP-FPM socket active at: ${FOUND_SOCK}"
	if [[ -r ${FOUND_SOCK} && -w ${FOUND_SOCK} ]]; then
		log_pass "Socket file permissions verified (read/write accessible)"
	else
		log_warn "Socket file exists but may require www-data group permissions: ${FOUND_SOCK}"
	fi
else
	# Check if php-fpm process is running
	if pgrep -f "php.*fpm" >/dev/null 2>&1; then
		log_warn "PHP-FPM daemon process detected, but dedicated pool socket (/run/php/php-fpm-pda.sock) not found."
	else
		log_fail "PHP-FPM daemon is NOT running or socket is missing."
	fi
fi

# ------------------------------------------------------------------------------
# 2. MariaDB Database Connectivity & Schema Verification
# ------------------------------------------------------------------------------
echo -e "\n${BLUE}[Check 2/3] Verifying MariaDB Database Connection & Schema...${NC}"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-pdns_admin}"
DB_USER="${DB_USER:-pdns_admin_user}"
DB_PASS="${DB_PASS:-}"

# Check client command
MARIADB_CMD=""
if command -v mariadb >/dev/null 2>&1; then
	MARIADB_CMD="mariadb"
elif command -v mysql >/dev/null 2>&1; then
	MARIADB_CMD="mysql"
fi

if [[ -z ${MARIADB_CMD} ]]; then
	log_warn "Neither 'mariadb' nor 'mysql' client CLI found on PATH. Skipping CLI connection test."
else
	MYSQL_ARGS=("-h" "${DB_HOST}" "-P" "${DB_PORT}" "-u" "${DB_USER}")
	if [[ -n ${DB_PASS} ]]; then
		MYSQL_ARGS+=("-p${DB_PASS}")
	fi

	if "${MARIADB_CMD}" "${MYSQL_ARGS[@]}" -e "SELECT 1;" >/dev/null 2>&1; then
		log_pass "MariaDB connection successful (${DB_USER}@${DB_HOST}:${DB_PORT})"

		# Check if database exists
		if "${MARIADB_CMD}" "${MYSQL_ARGS[@]}" -e "USE \`${DB_NAME}\`;" >/dev/null 2>&1; then
			log_pass "Target database '${DB_NAME}' exists and is accessible"

			# Check key tables
			TABLES_CHECK=$("${MARIADB_CMD}" "${MYSQL_ARGS[@]}" -N -e "
        SELECT COUNT(*) FROM information_schema.tables 
        WHERE table_schema = '${DB_NAME}' AND table_name IN ('users', 'settings', 'zones', 'pdns_servers');
      " 2>/dev/null || echo "0")

			if [[ ${TABLES_CHECK} -ge 3 ]]; then
				log_pass "Core metadata schema tables verified (${TABLES_CHECK}/4 detected)"
			else
				log_warn "Database exists but some core tables are missing (${TABLES_CHECK}/4 detected). Complete web setup at /install."
			fi
		else
			log_warn "Database '${DB_NAME}' does not exist or user lacks access. Please run installer."
		fi
	else
		log_warn "Could not authenticate to MariaDB with ${DB_USER}@${DB_HOST}. Verify DB_PASS or create user."
	fi
fi

# ------------------------------------------------------------------------------
# 3. PowerDNS REST API Connectivity & Telemetry
# ------------------------------------------------------------------------------
echo -e "\n${BLUE}[Check 3/3] Verifying PowerDNS Authoritative REST API...${NC}"

PDNS_API_URL="${PDNS_API_URL:-http://127.0.0.1:8081}"
PDNS_API_KEY="${PDNS_API_KEY:-}"
PDNS_SERVER_ID="${PDNS_SERVER_ID:-localhost}"

if [[ -z ${PDNS_API_KEY} ]]; then
	# Try checking common config locations
	for cfg in "/etc/powerdns/pdns.conf" "/etc/pdns/pdns.conf"; do
		if [[ -r ${cfg} ]]; then
			FOUND_KEY=$(grep -E '^[[:space:]]*api-key=' "${cfg}" 2>/dev/null | cut -d= -f2- | tr -d ' "\r\n' || true)
			if [[ -n ${FOUND_KEY} ]]; then
				PDNS_API_KEY="${FOUND_KEY}"
				break
			fi
		fi
	done
fi

if [[ -z ${PDNS_API_KEY} ]]; then
	log_warn "PDNS_API_KEY not supplied via environment or config. Set PDNS_API_KEY to test API connectivity."
else
	CLEAN_URL="${PDNS_API_URL%/}"
	ENDPOINT="${CLEAN_URL}/api/v1/servers/${PDNS_SERVER_ID}"

	if command -v curl >/dev/null 2>&1; then
		HTTP_RESP=$(curl -s -m 5 -w "\n%{http_code}" -H "X-API-Key: ${PDNS_API_KEY}" -H "Accept: application/json" "${ENDPOINT}" 2>/dev/null || echo -e "\n000")
		HTTP_BODY=$(echo "${HTTP_RESP}" | sed '$d')
		HTTP_CODE=$(echo "${HTTP_RESP}" | tail -n1)

		if [[ ${HTTP_CODE} == "200" ]]; then
			VERSION=$(echo "${HTTP_BODY}" | grep -o '"version"[[:space:]]*:[[:space:]]*"[^"]*"' | cut -d'"' -f4 || echo "Unknown")
			log_pass "PowerDNS REST API reachable at ${CLEAN_URL} (HTTP 200 OK)"
			log_pass "PowerDNS Authoritative Server daemon version: ${VERSION}"
		elif [[ ${HTTP_CODE} == "401" || ${HTTP_CODE} == "403" ]]; then
			log_fail "PowerDNS API returned authentication failure (HTTP ${HTTP_CODE}). Check X-API-Key."
		elif [[ ${HTTP_CODE} == "000" ]]; then
			log_fail "PowerDNS REST API unreachable at ${CLEAN_URL} (Connection refused or timeout). Is pdns service running with webserver=yes and api=yes?"
		else
			log_warn "PowerDNS API returned unexpected HTTP status: ${HTTP_CODE}"
		fi
	else
		log_warn "cURL is not installed. Unable to perform HTTP probe."
	fi
fi

# ------------------------------------------------------------------------------
# Health Summary & Exit Status
# ------------------------------------------------------------------------------
echo -e "\n${CYAN}================================================================================${NC}"
echo -e "Health Check Summary: ${GREEN}${PASS_COUNT} Passed${NC}, ${YELLOW}${WARN_COUNT} Warnings${NC}, ${RED}${FAIL_COUNT} Failed${NC}"
echo -e "${CYAN}================================================================================${NC}"

if [[ ${FAIL_COUNT} -gt 0 ]]; then
	echo -e "${RED}[ALERT] One or more critical health checks failed. Check error messages above.${NC}"
	exit 1
else
	echo -e "${GREEN}[OK] All primary system components are operational.${NC}"
	exit 0
fi
