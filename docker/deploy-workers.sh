#!/usr/bin/env bash

# Reinicia los workers de cola y el planificador de tareas gestionados por
# supervisor dentro del contenedor de Sail tras un despliegue.
#
# Uso: ./docker/deploy-workers.sh
# Requiere: Sail levantado (vendor/bin/sail up -d)

set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Reiniciando workers de cola (queue-worker:*)..."
vendor/bin/sail exec laravel.test supervisorctl restart "queue-worker:*"

echo "==> Reiniciando planificador de tareas (schedule-runner)..."
vendor/bin/sail exec laravel.test supervisorctl restart schedule-runner

echo "==> Estado de supervisor:"
vendor/bin/sail exec laravel.test supervisorctl status

echo "✔ Workers reiniciados."
