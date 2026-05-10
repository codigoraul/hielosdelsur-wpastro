# Integración Yoast SEO con Astro

## Configuración en WordPress

1. Instalar y activar **Yoast SEO** en WordPress
2. Configurar SEO para cada página/post en WordPress
3. Asegurarse que la API REST esté habilitada

## Uso en Astro

### Ejemplo básico en una página:

```astro
---
import Layout from '../layouts/Layout.astro';
import { getYoastSEO } from '../lib/wordpress.js';

// Obtener datos de Yoast para la página "inicio"
const yoastData = await getYoastSEO('inicio');
---

<Layout yoastData={yoastData}>
  <!-- Contenido de la página -->
</Layout>
```

### Actualizar Layout.astro:

```astro
---
import YoastSEO from '../components/YoastSEO.astro';

interface Props {
  title?: string;
  description?: string;
  yoastData?: any;
}

const { title, description, yoastData } = Astro.props;
---

<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width" />
    
    <!-- Si hay datos de Yoast, usarlos; sino usar props -->
    {yoastData ? (
      <YoastSEO yoastData={yoastData} />
    ) : (
      <>
        <title>{title || 'Hielos del Sur'}</title>
        {description && <meta name="description" content={description} />}
      </>
    )}
    
    <!-- Resto del head -->
  </head>
  <body>
    <slot />
  </body>
</html>
```

## Páginas en WordPress

Crear estas páginas en WordPress con Yoast SEO configurado:

- `inicio` - Para la home
- `nosotros` - Para la página nosotros
- `contacto` - Para contacto
- `catalogo` - Para catálogo
- `sillas` - Para categoría sillas
- `sillones` - Para categoría sillones
- `mesas` - Para categoría mesas
- `taburetes` - Para categoría taburetes
- `bancas` - Para categoría bancas
- `trabajos` - Para trabajos realizados
- `proyectos` - Para proyectos realizados

## Ventajas

✅ Gestión centralizada de SEO desde WordPress
✅ Preview de Google/Facebook en WordPress
✅ Análisis de legibilidad y SEO de Yoast
✅ Sitemap automático desde WordPress
✅ Breadcrumbs de Yoast
✅ Schema.org automático

## API Endpoint

Los datos se obtienen desde:
```
GET /wp-json/wp/v2/pages?slug=nombre-pagina&_fields=yoast_head_json
```

Yoast SEO expone automáticamente `yoast_head_json` con todos los meta tags.
