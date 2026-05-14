const WP_URL = 'https://hielosdelsur.cl/admin';

// Obtener datos de Yoast SEO para una página
export async function getYoastSEO(slug) {
  try {
    const res = await fetch(`${WP_URL}/wp-json/wp/v2/pages?slug=${slug}&_fields=yoast_head_json`);
    if (!res.ok) return null;
    const data = await res.json();
    if (!data || data.length === 0) return null;
    return data[0].yoast_head_json || null;
  } catch (error) {
    console.error('Error fetching Yoast SEO:', error);
    return null;
  }
}

// Obtener datos de Yoast SEO desde un producto específico
export async function getYoastSEOFromProduct(slug) {
  try {
    const res = await fetch(`${WP_URL}/wp-json/wp/v2/productos?slug=${slug}&_fields=yoast_head_json`);
    if (!res.ok) return null;
    const data = await res.json();
    if (!data || data.length === 0) return null;
    return data[0].yoast_head_json || null;
  } catch (error) {
    console.error('Error fetching Yoast SEO from product:', error);
    return null;
  }
}

// Obtener Yoast SEO para categoría de productos (taxonomy)
export async function getYoastSEOFromCategory(categorySlug) {
  try {
    // Primero obtener el término de la categoría
    const termRes = await fetch(`${WP_URL}/wp-json/wp/v2/categorias-producto?slug=${categorySlug}&_fields=yoast_head_json,id`);
    if (!termRes.ok) return null;
    const terms = await termRes.json();
    if (!terms || terms.length === 0) return null;
    return terms[0].yoast_head_json || null;
  } catch (error) {
    console.error('Error fetching Yoast SEO from category:', error);
    return null;
  }
}

async function getTerms() {
  const res = await fetch(`${WP_URL}/wp-json/wp/v2/categorias-producto?per_page=100`);
  if (!res.ok) return [];
  return res.json();
}

// Obtiene el ID de un término por su slug
async function getTermId(slug) {
  const terms = await getTerms();
  return terms.find(t => t.slug === slug)?.id || null;
}

export async function getGaleria(tipoSlug = '', catSlug = '') {
  const tipoRes = await fetch(`${WP_URL}/wp-json/wp/v2/galeria-tipo?slug=${tipoSlug}&per_page=1`);
  const tipos   = tipoRes.ok ? await tipoRes.json() : [];
  const tipoId  = tipos[0]?.id || null;
  if (!tipoId) return { items: [], categorias: [] };

  // Todas las categorías del tipo
  const catRes = await fetch(`${WP_URL}/wp-json/wp/v2/galeria-cat?per_page=100`);
  const cats   = catRes.ok ? await catRes.json() : [];

  // Paginar para traer todos (WP máx 100 por página)
  let data = [], page = 1;
  while (true) {
    const params = new URLSearchParams({
      per_page: '100', page: String(page), orderby: 'date', order: 'asc',
      _fields: 'id,title,imagen_url,galeria-tipo,galeria-cat,meta',
      'galeria-tipo': tipoId,
    });
    const res = await fetch(`${WP_URL}/wp-json/wp/v2/galeria?${params}`);
    if (!res.ok) break;
    const batch = await res.json();
    if (!Array.isArray(batch) || batch.length === 0) break;
    data = data.concat(batch);
    if (batch.length < 100) break;
    page++;
  }

  const catMap = Object.fromEntries(cats.map(c => [c.id, c]));

  const items = data.map(p => {
    const catIds = p['galeria-cat'] || [];
    const cat    = catIds.map(id => catMap[id]).find(c => c) || null;
    return {
      id:          p.id,
      titulo:      p.title?.rendered || '',
      imagen:      p.imagen_url || '',
      categoria:   cat?.name || '',
      catSlug:     cat?.slug || '',
      descripcion: p.meta?.descripcion || '',
      anio:        p.meta?.anio || '',
      cliente:     p.meta?.cliente || '',
    };
  });

  const categorias = cats.filter(c => items.some(i => i.catSlug === c.slug));

  return { items, categorias };
}

export async function getProductos(categoriaSlug = '') {
  const terms = await getTerms();

  // Mapa id → término
  const termMap = Object.fromEntries(terms.map(t => [t.id, t]));

  let termId = null;
  if (categoriaSlug) {
    termId = terms.find(t => t.slug === categoriaSlug)?.id || null;
  }

  const params = new URLSearchParams({
    per_page: '100',
    orderby: 'id',
    order: 'asc',
    _fields: 'id,slug,title,meta,imagen_url,galeria_urls,categorias-producto',
    ...(termId && { 'categorias-producto': termId }),
  });

  const res = await fetch(`${WP_URL}/wp-json/wp/v2/productos?${params}`);
  if (!res.ok) return [];
  const data = await res.json();

  return data.map(p => {
    const termIds = p['categorias-producto'] || [];

    // Subcategoría = cualquier término que no sea la categoría principal de página
    const mainSlugs = ['sillas', 'sillones', 'mesas', 'bancas'];
    const subterm = termIds
      .map(id => termMap[id])
      .filter(t => t && !mainSlugs.includes(t.slug))[0];

    return {
      id: p.id,
      slug: p.slug,
      titulo: p.title?.rendered || '',
      categoria: subterm?.name || '',
      articulo: p.meta?.articulo || '',
      procedencia: p.meta?.procedencia || '',
      garantia: p.meta?.garantia || '',
      plazo: p.meta?.plazo_entrega || '',
      nota: p.meta?.nota || '',
      pdf: p.meta?.ficha_pdf || '',
      imagen: p.imagen_url || '',
      caracteristicas: (() => {
        try { return JSON.parse(p.meta?.caracteristicas || '[]'); }
        catch { return []; }
      })(),
      galeria: p.galeria_urls || [],
    };
  });
}
