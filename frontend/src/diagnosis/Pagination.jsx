export default function Pagination({ meta, onPage, label = 'Paginación de diagnósticos' }) {
  return <nav className="pagination" aria-label={label}>
    <button disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>Anterior</button>
    <span>Página {meta.current_page} de {meta.last_page} · {meta.total} registros</span>
    <button disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)}>Siguiente</button>
  </nav>
}