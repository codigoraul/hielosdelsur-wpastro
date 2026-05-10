import { useState, useCallback } from 'react';

export default function GaleriaGrid({ items = [], categorias = [] }) {
  const [filtroActivo, setFiltroActivo]   = useState('todas');
  const [filtroVisible, setFiltroVisible] = useState('todas');
  const [saliendo, setSaliendo]           = useState(false);
  const [animKey, setAnimKey]             = useState(0);
  const [lightbox, setLightbox]           = useState(null); // { img, titulo, descripcion, anio, cliente }

  const cambiarFiltro = useCallback((f) => {
    if (f === filtroActivo) return;
    setSaliendo(true);
    setTimeout(() => {
      setFiltroVisible(f);
      setFiltroActivo(f);
      setAnimKey(k => k + 1);
      setSaliendo(false);
    }, 200);
  }, [filtroActivo]);

  const filtrados = filtroVisible === 'todas'
    ? items
    : items.filter(p => p.categoria === filtroVisible);

  // Calcular conteo por categoría
  const getCount = (categoria) => {
    if (categoria === 'todas') return items.length;
    return items.filter(p => p.categoria === categoria).length;
  };

  return (
    <div>
      {/* Filtros */}
      {categorias.length > 0 && (
        <div style={{ display:'flex', alignItems:'center', justifyContent:'center', flexWrap:'wrap', marginBottom:'2.5rem', gap:'0' }}>
          {['todas', ...categorias.map(c => c.name)].map((f, i) => {
            const count = getCount(f);
            return (
              <span key={f} style={{ display:'flex', alignItems:'center' }}>
                {i > 0 && <span style={{ color:'#ccc', margin:'0 0.6rem', fontWeight:'300' }}>/</span>}
                <button
                  onClick={() => cambiarFiltro(f)}
                  className="filtro-btn"
                  style={{
                    background:'none', border:'none', cursor:'pointer',
                    fontSize:'0.72rem', fontWeight: filtroActivo === f ? '800' : '500',
                    letterSpacing:'0.12em', textTransform:'uppercase',
                    color: filtroActivo === f ? '#1c1917' : '#555555',
                    padding:'0.25rem 0.1rem',
                    borderBottom: filtroActivo === f ? '2px solid #8B7355' : '2px solid transparent',
                    transition:'all 0.2s',
                    display:'inline-flex', alignItems:'center',
                    position:'relative',
                  }}
                >
                  <span className="filtro-count" style={{
                    position:'absolute',
                    bottom:'100%',
                    left:'50%',
                    marginBottom:'0.25rem',
                    background: filtroActivo === f ? '#8B7355' : '#57534e',
                    color: '#fff',
                    fontSize:'0.65rem',
                    fontWeight:'700',
                    padding:'0.15rem 0.45rem',
                    borderRadius:'3px',
                    minWidth:'22px',
                    textAlign:'center',
                  }}>
                    {count}
                  </span>
                  <span>{f === 'todas' ? 'Todas' : f}</span>
                </button>
              </span>
            );
          })}
        </div>
      )}

      {/* Grid */}
      <div style={{
        display:'grid', gap:'0.75rem',
        transition:'opacity 0.2s ease, transform 0.2s ease',
        opacity: saliendo ? 0 : 1,
        transform: saliendo ? 'translateX(-16px)' : 'translateX(0)',
      }} className="galeria-grid">
        {filtrados.map((item, idx) => (
          <div
            key={`${animKey}-${item.id}`}
            onClick={() => setLightbox(item)}
            style={{
              cursor:'pointer',
              animation:'gEntrada 0.4s cubic-bezier(0.25,0.46,0.45,0.94) both',
              animationDelay:`${idx * 30}ms`,
            }}
          >
            {/* Imagen */}
            <div style={{ overflow:'hidden', position:'relative', aspectRatio:'4/3', background:'#eee', borderRadius:'2px' }}>
              {item.imagen && (
                <img
                  src={item.imagen} alt={item.titulo}
                  style={{ width:'100%', height:'100%', objectFit:'cover', display:'block', transition:'transform 0.4s ease' }}
                  onMouseOver={e => e.currentTarget.style.transform='scale(1.06)'}
                  onMouseOut={e => e.currentTarget.style.transform='scale(1)'}
                />
              )}
            </div>
            {/* Título siempre visible debajo */}
            <p style={{
              margin:'0.6rem 0 1.25rem',
              fontSize:'0.72rem', fontWeight:'700',
              letterSpacing:'0.08em', textTransform:'uppercase',
              color:'#1c1917',
            }}>
              {item.titulo}
            </p>
          </div>
        ))}
      </div>

      {/* Lightbox */}
      {lightbox && (
        <div
          onClick={() => setLightbox(null)}
          style={{
            position:'fixed', inset:0, background:'rgba(0,0,0,0.88)', zIndex:200,
            display:'flex', alignItems:'center', justifyContent:'center', padding:'1rem',
            animation:'fadeIn 0.2s ease',
          }}
        >
          <div
            onClick={e => e.stopPropagation()}
            style={{
              background:'#fff', maxWidth:'1100px', width:'100%', maxHeight:'92vh',
              overflow:'auto', borderRadius:'2px',
              animation:'slideUp 0.25s ease', position:'relative',
            }}
          >
            {/* Botón cerrar */}
            <button
              onClick={() => setLightbox(null)}
              style={{ position:'sticky', top:'0.75rem', left:'calc(100% - 3rem)', zIndex:10, background:'rgba(0,0,0,0.55)', border:'none', borderRadius:'50%', width:'36px', height:'36px', cursor:'pointer', fontSize:'1rem', color:'#fff', display:'flex', alignItems:'center', justifyContent:'center', marginLeft:'auto', marginRight:'0.75rem', marginTop:'0.75rem', flexShrink:0 }}
            >✕</button>
            {/* Imagen grande */}
            <div style={{ background:'#111', display:'flex', alignItems:'center', justifyContent:'center', marginTop:'-52px' }}>
              {lightbox.imagen && (
                <img src={lightbox.imagen} alt={lightbox.titulo}
                  style={{ width:'100%', maxHeight:'72vh', objectFit:'contain', display:'block' }} />
              )}
            </div>
            {/* Detalles abajo */}
            <div style={{ padding:'1.5rem 2rem 2rem' }}>
              <h2 style={{ fontSize:'1.25rem', fontWeight:'800', color:'#1c1917', marginBottom:'0.4rem', textTransform:'uppercase', letterSpacing:'0.05em' }}>
                {lightbox.titulo}
              </h2>
              {(lightbox.anio || lightbox.cliente) && (
                <p style={{ fontSize:'0.82rem', color:'#8B7355', fontWeight:'600', marginBottom:'0.75rem' }}>
                  {[lightbox.cliente, lightbox.anio].filter(Boolean).join(' · ')}
                </p>
              )}
              {lightbox.descripcion && (
                <p style={{ fontSize:'0.9rem', color:'#57534e', lineHeight:'1.75', marginBottom:0 }}>
                  {lightbox.descripcion}
                </p>
              )}
            </div>
          </div>
        </div>
      )}

      <style>{`
        .galeria-grid { grid-template-columns: repeat(4,1fr); }
        @media (max-width:1200px) { .galeria-grid { grid-template-columns: repeat(3,1fr); } }
        @media (max-width:991px) { .galeria-grid { grid-template-columns: repeat(2,1fr); } }
        
        .filtro-count {
          opacity: 0;
          transform: translateX(-50%) translateY(16px);
          transition: opacity 0.35s ease, transform 0.35s ease;
        }
        
        .filtro-btn:hover .filtro-count {
          opacity: 1;
          transform: translateX(-50%) translateY(0);
        }
        
        @keyframes gEntrada { from { opacity:0; transform:perspective(500px) rotateY(-40deg) translateX(20px); } to { opacity:1; transform:perspective(500px) rotateY(0) translateX(0); } }
        @keyframes fadeIn   { from { opacity:0 } to { opacity:1 } }
        @keyframes slideUp  { from { opacity:0; transform:translateY(20px) } to { opacity:1; transform:translateY(0) } }
      `}</style>
    </div>
  );
}
