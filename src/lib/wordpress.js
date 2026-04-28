const WP_URL = 'http://localhost:10053';

export async function getProductos(categoria = '') {
  const params = new URLSearchParams({
    per_page: '100',
    _fields: 'id,slug,title,meta,imagen_url',
    ...(categoria && { categoria_producto: categoria }),
  });

  const res = await fetch(`${WP_URL}/wp-json/wp/v2/productos?${params}`);
  if (!res.ok) return [];
  const data = await res.json();

  return data.map(p => ({
    id: p.id,
    slug: p.slug,
    titulo: p.title?.rendered || '',
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
  }));
}
