#!/usr/bin/env bash
# One-command server bootstrap for EvoDrive (Ride-Hailing SaaS).
# Run as root (or with sudo). Idempotent where possible.
#
# Usage: sudo ./scripts/server-setup.sh [OPTIONS]
#   --swap SIZE_MB    Create swap file (e.g. 2048 for 2GB). Default: 0 (skip).
#   --app-dir DIR     App directory. Default: /var/www/evodrive.
#   --user USER       Owner of app dir (for deploy). Default: current user or deploy.

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/evodrive}"
DEPLOY_USER="${DEPLOY_USER:-}"
SWAP_SIZE_MB="${SWAP_SIZE_MB:-0}"

while [[ $# -gt 0 ]]; do
  case $1 in
    --swap)       SWAP_SIZE_MB="$2"; shift 2 ;;
    --app-dir)    APP_DIR="$2"; shift 2 ;;
    --user)       DEPLOY_USER="$2"; shift 2 ;;
    *)            echo "Unknown option: $1"; exit 1 ;;
  esac
done

if [[ -n "${DEPLOY_USER:-}" ]]; then
  APP_OWNER="$DEPLOY_USER"
else
  APP_OWNER="${SUDO_USER:-$USER}"
  if [[ -z "$APP_OWNER" ]] || [[ "$APP_OWNER" == "root" ]]; then
    APP_OWNER="root"
  fi
fi

echo "[server-setup] App directory: $APP_DIR (owner: $APP_OWNER)"
echo "[server-setup] Swap: ${SWAP_SIZE_MB}MB"

# ---------------------------------------------------------------------------
# Docker + Docker Compose (official)
# ---------------------------------------------------------------------------
if ! command -v docker &>/dev/null; then
  echo "[server-setup] Installing Docker..."
  apt-get update
  apt-get install -y ca-certificates curl
  install -m 0755 -d /etc/apt/keyrings
  DOCKER_OS="ubuntu"
  if [[ -f /etc/os-release ]]; then
    source /etc/os-release
    case "${ID:-}" in
      debian) DOCKER_OS=debian ;;
      ubuntu) DOCKER_OS=ubuntu ;;
      *) DOCKER_OS=ubuntu ;;
    esac
  fi
  curl -fsSL "https://download.docker.com/linux/${DOCKER_OS}/gpg" | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
  chmod a+r /etc/apt/keyrings/docker.gpg
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/${DOCKER_OS} $(. /etc/os-release && echo "${VERSION_CODENAME:-$UBUNTU_CODENAME}") stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
  apt-get update
  apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  systemctl enable --now docker
else
  echo "[server-setup] Docker already installed."
fi

if ! docker compose version &>/dev/null; then
  echo "[server-setup] Installing Docker Compose plugin..."
  apt-get update
  apt-get install -y docker-compose-plugin || true
fi

# Allow deploy user to run docker without sudo (optional)
if [[ -n "${DEPLOY_USER:-}" ]] && id "$DEPLOY_USER" &>/dev/null; then
  usermod -aG docker "$DEPLOY_USER" 2>/dev/null || true
fi
if [[ -n "${SUDO_USER:-}" ]] && [[ "$SUDO_USER" != "root" ]]; then
  usermod -aG docker "$SUDO_USER" 2>/dev/null || true
fi

# ---------------------------------------------------------------------------
# UFW
# ---------------------------------------------------------------------------
if command -v ufw &>/dev/null; then
  echo "[server-setup] Configuring UFW..."
  ufw default deny incoming
  ufw default allow outgoing
  ufw allow 22/tcp   # SSH
  ufw allow 80/tcp
  ufw allow 443/tcp
  ufw --force enable || true
else
  echo "[server-setup] UFW not found; skipping firewall."
fi

# ---------------------------------------------------------------------------
# App directory and permissions
# ---------------------------------------------------------------------------
mkdir -p "$APP_DIR"
chown -R "$APP_OWNER:$APP_OWNER" "$APP_DIR"
echo "[server-setup] Created $APP_DIR (owner: $APP_OWNER)"

# ---------------------------------------------------------------------------
# Optional swap
# ---------------------------------------------------------------------------
if [[ -n "${SWAP_SIZE_MB:-}" ]] && [[ "${SWAP_SIZE_MB:-0}" -gt 0 ]]; then
  SWAP_FILE="/swapfile"
  if [[ ! -f "$SWAP_FILE" ]] || [[ $(stat -c%s "$SWAP_FILE" 2>/dev/null || echo 0) -eq 0 ]]; then
    echo "[server-setup] Creating ${SWAP_SIZE_MB}MB swap at $SWAP_FILE..."
    fallocate -l "${SWAP_SIZE_MB}M" "$SWAP_FILE" 2>/dev/null || dd if=/dev/zero of="$SWAP_FILE" bs=1M count="$SWAP_SIZE_MB"
    chmod 600 "$SWAP_FILE"
    mkswap "$SWAP_FILE"
    swapon "$SWAP_FILE"
    if ! grep -q "$SWAP_FILE" /etc/fstab; then
      echo "$SWAP_FILE none swap sw 0 0" >> /etc/fstab
    fi
  else
    echo "[server-setup] Swap file already exists."
  fi
fi

echo "[server-setup] Done. Next: clone repo into $APP_DIR, copy deploy/env.production.example to .env, set secrets, then run: docker compose up -d"
