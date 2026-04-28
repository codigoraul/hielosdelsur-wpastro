import { useState, useRef, useEffect } from 'react';

const COLS = 3;

export default function CatalogGrid({ productos = [], filtros = [] }) {
  const [filtroActivo, setFiltroActivo] = useState('todas');
  const [productoActivo, setProductoActivo] = useState(null);
  const panelRef = useRef(null);

  const filtrados = filtroActivo === 'todas'
    ? productos
    : productos.filter(p => p.categoria === filtroActivo);

  // Calcular en qué fila está el producto activo
  const idxActivo = productoActivo
    ? filtrados.findIndex(p => p.id === productoActivo.id)
    : -1;
  const filaActiva = idxActivo >= 0 ? Math.floor(idxActivo / COLS) : -1;

  const handleClick = (producto) => {
    if (productoActivo?.id === producto.id) {
      setProductoActivo(null);
    } else {
      setProductoActivo(producto);
    }
  };

  // Scroll suave al panel cuando abre
  useEffect(() => {
    if (productoActivo && panelRef.current) {
      setTimeout(() => {
        panelRef.current?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }, 100);
    }
  }, [productoActivo]);

  // Construir filas
  const filas = [];
  for (let i = 0; i < filtrados.length; i += COLS) {
    filas.push(filtrados.slice(i, i + COLS));
  }

  return (
    <div>
      {/* Filtros estilo original — texto con separador / */}
      <div style={{
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        flexWrap: 'wrap', marginBottom: '2.5rem', gap: '0'
      }}>
        {['todas', ...filtros].map((f, i) => (
          <span key={f} style={{ display: 'flex', alignItems: 'center' }}>
            {i > 0 && (
              <span style={{ color: '#ccc', margin: '0 0.6rem', fontWeight: '300' }}>/</span>
            )}
            <button
              onClick={() => { setFiltroActivo(f); setProductoActivo(null); }}
              style={{
                background: 'none', border: 'none', cursor: 'pointer',
                fontSize: '0.72rem', fontWeight: filtroActivo === f ? '800' : '500',
                letterSpacing: '0.12em', textTransform: 'uppercase',
                color: filtroActivo === f ? '#1c1917' : '#9a9a9a',
                padding: '0.25rem 0.1rem',
                borderBottom: filtroActivo === f ? '2px solid #8B7355' : '2px solid transparent',
                transition: 'all 0.2s',
              }}
            >
              {f === 'todas' ? 'Todas' : f}
            </button>
          </span>
        ))}
      </div>

      {/* Grid por filas */}
      <div>
        {filas.map((fila, filaIdx) => (
          <div key={filaIdx}>
            {/* Fila de productos */}
            <div style={{
              display: 'grid',
              gridTemplateColumns: `repeat(${COLS}, 1fr)`,
              gap: '1.5rem',
              marginBottom: filaActiva === filaIdx ? 0 : '1.5rem',
            }}>
              {fila.map(producto => (
                <div
                  key={producto.id}
                  onClick={() => handleClick(producto)}
                  style={{ cursor: 'pointer' }}
                >
                  {/* Imagen — sin borde, fondo gris claro */}
                  <div style={{
                    position: 'relative',
                    overflow: 'hidden',
                    background: '#FAFAFA',
                    aspectRatio: '4/3',
                    borderRadius: '2px',
                  }}>
                    {producto.imagen && (
                      <img
                        src={producto.imagen}
                        alt={producto.titulo}
                        style={{
                          width: '100%', height: '100%', objectFit: 'contain',
                          transition: 'transform 0.4s ease',
                          padding: '1rem',
                        }}
                        onMouseOver={e => e.currentTarget.style.transform = 'scale(1.05)'}
                        onMouseOut={e => e.currentTarget.style.transform = 'scale(1)'}
                      />
                    )}
                    {/* Overlay hover */}
                    <div style={{
                      position: 'absolute', inset: 0,
                      background: 'rgba(0,0,0,0.45)',
                      display: 'flex', alignItems: 'center', justifyContent: 'center',
                      opacity: productoActivo?.id === producto.id ? 1 : 0,
                      transition: 'opacity 0.3s',
                    }}
                      onMouseOver={e => { if (productoActivo?.id !== producto.id) e.currentTarget.style.opacity = '1'; }}
                      onMouseOut={e => { if (productoActivo?.id !== producto.id) e.currentTarget.style.opacity = '0'; }}
                    >
                      <span style={{
                        color: '#fff', fontSize: '0.7rem', fontWeight: '700',
                        letterSpacing: '0.15em', textTransform: 'uppercase',
                        padding: '0.6rem 1.5rem',
                        border: '1px solid rgba(255,255,255,0.7)',
                      }}>
                        {productoActivo?.id === producto.id ? '✕ Cerrar' : 'Ver detalles'}
                      </span>
                    </div>
                  </div>
                  {/* Título */}
                  <p style={{
                    marginTop: '0.75rem',
                    fontSize: '0.85rem',
                    fontWeight: '600',
                    color: '#1c1917',
                  }}>
                    {producto.titulo}
                  </p>
                </div>
              ))}
              {/* Rellenar espacios vacíos en última fila */}
              {fila.length < COLS && Array.from({ length: COLS - fila.length }).map((_, i) => (
                <div key={`empty-${i}`} />
              ))}
            </div>

            {/* Panel inline — se inserta después de la fila del producto activo */}
            {filaActiva === filaIdx && productoActivo && (
              <div
                ref={panelRef}
                style={{
                  margin: '1.5rem 0',
                  background: '#FAFAFA',
                  border: 'none',
                  boxShadow: 'none',
                  overflow: 'hidden',
                  animation: 'panelSlide 0.35s ease',
                }}
              >
                <div style={{
                  display: 'grid',
                  gridTemplateColumns: '55% 45%',
                  minHeight: '660px',
                }}>
                  {/* Imagen grande */}
                  <div style={{
                    background: '#FAFAFA',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    padding: '1.5rem',
                  }}>
                    {productoActivo.imagen && (
                      <img
                        src={productoActivo.imagen}
                        alt={productoActivo.titulo}
                        style={{ width: '100%', height: '100%', objectFit: 'contain', maxHeight: '620px' }}
                      />
                    )}
                  </div>

                  {/* Detalles */}
                  <div style={{ padding: '2.5rem', position: 'relative', background: '#FAFAFA' }}>
                    {/* Cerrar */}
                    <button
                      onClick={() => setProductoActivo(null)}
                      style={{
                        position: 'absolute', top: '1rem', right: '1rem',
                        width: '32px', height: '32px', borderRadius: '50%',
                        border: '1px solid #e7e5e4', background: '#fff',
                        cursor: 'pointer', fontSize: '1rem', color: '#78716c',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                      }}
                    >✕</button>

                    <h2 style={{ fontSize: '1.3rem', fontWeight: '800', color: '#1c1917', marginBottom: '0.25rem' }}>
                      {productoActivo.titulo}
                    </h2>
                    {productoActivo.articulo && (
                      <p style={{ fontSize: '0.85rem', fontWeight: '700', color: '#8B7355', marginBottom: '1.5rem' }}>
                        {productoActivo.articulo}
                      </p>
                    )}

                    {productoActivo.caracteristicas?.length > 0 && (
                      <div style={{ marginBottom: '1.5rem' }}>
                        <p style={{ fontSize: '0.75rem', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '0.1em', color: '#78716c', marginBottom: '0.5rem' }}>
                          Características:
                        </p>
                        {productoActivo.caracteristicas.map((c, i) => (
                          <p key={i} style={{ fontSize: '0.85rem', color: '#44403c', marginBottom: '0.25rem' }}>
                            • {c}
                          </p>
                        ))}
                      </div>
                    )}

                    <div style={{ display: 'flex', flexDirection: 'column', gap: '0.4rem', marginBottom: '1.5rem' }}>
                      {productoActivo.procedencia && (
                        <p style={{ fontSize: '0.85rem', color: '#44403c' }}>
                          <strong>Procedencia:</strong> {productoActivo.procedencia}
                        </p>
                      )}
                      {productoActivo.garantia && (
                        <p style={{ fontSize: '0.85rem', color: '#44403c' }}>
                          <strong>Garantía:</strong> {productoActivo.garantia}
                        </p>
                      )}
                      {productoActivo.plazo && (
                        <p style={{ fontSize: '0.85rem', color: '#44403c' }}>
                          <strong>Plazo de entrega:</strong> {productoActivo.plazo}
                        </p>
                      )}
                    </div>

                    {productoActivo.nota && (
                      <p style={{ fontSize: '0.78rem', color: '#a8a29e', marginBottom: '1.5rem', lineHeight: 1.6 }}>
                        {productoActivo.nota}
                      </p>
                    )}

                    {productoActivo.pdf && (
                      <a
                        href={productoActivo.pdf}
                        target="_blank"
                        rel="noopener noreferrer"
                        style={{
                          display: 'inline-flex', alignItems: 'center', gap: '0.5rem',
                          fontSize: '0.7rem', fontWeight: '800', letterSpacing: '0.15em',
                          textTransform: 'uppercase', color: '#8B7355',
                          textDecoration: 'none', borderBottom: '2px solid #8B7355',
                          paddingBottom: '2px',
                        }}
                      >
                        📄 Ver Ficha Técnica
                      </a>
                    )}
                  </div>
                </div>
              </div>
            )}
          </div>
        ))}
      </div>

      <style>{`
        @keyframes panelSlide {
          from { opacity: 0; transform: translateY(-12px); }
          to   { opacity: 1; transform: translateY(0); }
        }
      `}</style>
    </div>
  );
}
