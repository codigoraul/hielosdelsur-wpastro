# Instrucciones de Deploy

## Configurar Secrets en GitHub

1. Ve a tu repositorio en GitHub
2. Settings → Secrets and variables → Actions
3. Agrega estos 3 secrets:

### FTP_SERVER
```
ftp.hielosdelsurspa.cl
```

### FTP_USERNAME
```
conexion@hielosdelsur.cl
```

### FTP_PASSWORD
La contraseña que configuraste para el usuario FTP

## Estructura en el servidor

```
/public_html/
├── prueba/          ← Aquí se sube Astro (dist/)
└── admin/           ← Aquí va WordPress (manual)
```

## Cómo funciona

1. Haces push a la rama `main`
2. GitHub Actions automáticamente:
   - Instala dependencias
   - Construye el sitio (`npm run build`)
   - Sube la carpeta `dist/` a `/public_html/prueba/`

## Deploy manual (si es necesario)

```bash
# Construir localmente
npm run build

# Subir manualmente por FTP
# Subir contenido de dist/ a /public_html/prueba/
```

## WordPress

WordPress debe subirse manualmente a `/public_html/admin/` con:
- Archivos de WordPress
- Base de datos configurada
- wp-config.php configurado
- API REST habilitada

## URLs finales

- Frontend Astro: https://hielosdelsur.cl/prueba
- Backend WordPress: https://hielosdelsur.cl/admin
- API WordPress: https://hielosdelsur.cl/admin/wp-json/
