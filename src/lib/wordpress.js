const WP_URL = 'http://localhost:10053';

// Cache de términos para no hacer múltiples requests
let _termCache = null;
async function getTerms() {
  if (_termCache) return _termCache;
  const res = await fetch(`${WP_URL}/wp-json/wp/v2/categorias-producto?per_page=100`);
  if (!res.ok) return [];
  _termCache = await res.json();
  return _termCache;
}

// Obtiene el ID de un término por su slug
async function getTermId(slug) {
  const terms = await getTerms();
  return terms.find(t => t.slug === slug)?.id || null;
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
    _fields: 'id,slug,title,meta,imagen_url,categorias-producto',
    ...(termId && { 'categorias-producto': termId }),
  });

  const res = await fetch(`${WP_URL}/wp-json/wp/v2/productos?${params}`);
  if (!res.ok) return [];
  const data = await res.json();

  return data.map(p => {
    const termIds = p['categorias-producto'] || [];

    // Subcategoría = cualquier término que no sea la categoría principal de página
    const mainSlugs = ['sillas', 'sillones', 'taburetes', 'mesas', 'bancas'];
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
      galeria: (() => {
        try { return JSON.parse(p.meta?.galeria || '[]'); }
        catch { return []; }
      })(),
    };
  });
}
