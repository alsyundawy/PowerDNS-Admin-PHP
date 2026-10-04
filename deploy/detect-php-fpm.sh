#!/usr/bin/env bash
set -Eeuo pipefail

################################################################################
# PowerDNS-Admin-PHP - PHP-FPM Version Auto-Detector & Socket Linker
# ==============================================================================
# Detects installed PHP-FPM version, pool configurations, and ensures
# the universal socket /run/php/php-fpm-pda.sock is accurately linked.
################################################################################

GREEN='\033[1;32m'
YELLOW='\033[1;33m'
BLUE='\033[1;34m'
CYAN='\033[1;36m'
NC='\033[0m'

echo -e "${CYAN}================================================================================${NC}"
echo -e "${GREEN} PowerDNS-Admin-PHP - PHP-FPM Auto-Detection & Socket Helper ${NC}"
echo -e "${CYAN}================================================================================${NC}"

# 1. Detect CLI PHP Version
CLI_PHP_VER=""
if command -v php &>/dev/null; then
	CLI_PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"
fi

if [[ -n ${CLI_PHP_VER} ]]; then
	echo -e "${BLUE}[INFO] Active PHP CLI:${NC} version ${GREEN}${CLI_PHP_VER}${NC}"
else
	echo -e "${YELLOW}[WARN] PHP CLI not found in system PATH.${NC}"
fi

# 2. Detect Available PHP-FPM Versions in /etc/php
INSTALLED_FPM_VERSIONS=()
if [[ -d "/etc/php" ]]; then
	FPM_SEARCH_OUTPUT="$(find /etc/php -maxdepth 2 -type d -name "fpm" 2>/dev/null || true)"
	if [[ -n ${FPM_SEARCH_OUTPUT} ]]; then
		SORTED_FPM_DIRS="$(echo "${FPM_SEARCH_OUTPUT}" | sort -V)"
		while IFS= read -r fpm_dir; do
			[[ -z ${fpm_dir} ]] && continue
			fpm_parent="$(dirname "${fpm_dir}")"
			ver="$(basename "${fpm_parent}")"
			INSTALLED_FPM_VERSIONS+=("${ver}")
		done <<<"${SORTED_FPM_DIRS}"
	fi
fi

if [[ ${#INSTALLED_FPM_VERSIONS[@]} -gt 0 ]]; then
	echo -e "${BLUE}[INFO] Installed PHP-FPM versions:${NC} ${GREEN}${INSTALLED_FPM_VERSIONS[*]}${NC}"
else
	echo -e "${YELLOW}[WARN] Directory /etc/php/*/fpm not found.${NC}"
fi

# 3. Determine Best Target PHP Version
TARGET_VER="${CLI_PHP_VER}"
if [[ -z ${TARGET_VER} && ${#INSTALLED_FPM_VERSIONS[@]} -gt 0 ]]; then
	TARGET_VER="${INSTALLED_FPM_VERSIONS[-1]}"
fi

if [[ -z ${TARGET_VER} ]]; then
	TARGET_VER="8.2"
	echo -e "${YELLOW}[WARN] Using fallback target version:${NC} ${TARGET_VER}"
fi

echo -e "${CYAN}[TARGET] Recommended PHP-FPM version:${NC} ${GREEN}${TARGET_VER}${NC}"

POOL_SOCK="/run/php/php${TARGET_VER}-fpm-pda.sock"
UNIVERSAL_SOCK="/run/php/php-fpm-pda.sock"

# 4. Check & Link Socket if Running with Root Privileges
CURRENT_USER_ID=""
if [[ -n ${EUID:-} ]]; then
	CURRENT_USER_ID="${EUID}"
else
	CURRENT_USER_ID="$(id -u 2>/dev/null || echo 1000)"
fi

if [[ ${CURRENT_USER_ID} -eq 0 ]]; then
	mkdir -p /run/php
	if [[ -S ${POOL_SOCK} || -f "/etc/php/${TARGET_VER}/fpm/pool.d/pda.conf" ]]; then
		ln -sfn "${POOL_SOCK}" "${UNIVERSAL_SOCK}"
		echo -e "${GREEN}[OK] Universal symlink created:${NC} ${UNIVERSAL_SOCK} -> ${POOL_SOCK}"
	else
		echo -e "${YELLOW}[INFO] Socket not active yet. Ensure php${TARGET_VER}-fpm service is running:${NC}"
		echo -e "       systemctl restart php${TARGET_VER}-fpm"
	fi
else
	echo -e "${YELLOW}[INFO] Run this script with 'sudo' to create the symlink automatically.${NC}"
fi

echo -e "\n${CYAN}Nginx Configuration Information:${NC}"
echo -e "  - FastCGI Pass: ${YELLOW}unix:${UNIVERSAL_SOCK}${NC} (or unix:${POOL_SOCK})"
echo -e "  - Pool File   : ${YELLOW}/etc/php/${TARGET_VER}/fpm/pool.d/pda.conf${NC}"
echo ""
