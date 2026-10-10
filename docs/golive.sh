#!/bin/sh
# Go-live en la raiz. Ejecutar en la carpeta del proyecto DESPUES de: cuenta FTP nueva + secrets de GitHub actualizados.
set -e
sed -i.bak '/^        env:$/,/^          SITE_BASE: \/muestra$/d' .github/workflows/deploy.yml && rm -f .github/workflows/deploy.yml.bak
cp docs/htaccess-raiz.txt public/.htaccess
grep -n "SITE_BASE" .github/workflows/deploy.yml && echo "ERROR: SITE_BASE sigue en el workflow" && exit 1
git add -A -- . ':!*.bak'
git commit -m "Go-live: sitio en la raiz (sin SITE_BASE, htaccess con 301/410)"
echo "Listo. Ahora: git push origin main"
