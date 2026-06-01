#!/usr/bin/env bash
set -euo pipefail

echo "============================================"
echo "  Novare Brindes - Preview local"
echo "============================================"

if ! command -v php >/dev/null 2>&1; then
    echo ""
    echo "[ERRO] PHP 8.x nao encontrado no PATH."
    echo "Instale o PHP (ex.: 'sudo apt install php-cli php-mysql' ou via brew)"
    echo "e rode novamente. Veja a secao 'Preview local' do README.md."
    exit 1
fi

echo "Usando PHP: $(command -v php)"
echo "Iniciando em http://localhost:8000   (CTRL+C para parar)"
echo ""
exec php -S localhost:8000 -t public
