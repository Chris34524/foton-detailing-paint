import AppIcon from '../components/AppIcon'
export default function Pagination({ meta, onPage, label = 'Paginación de diagnósticos' }) {
  return <nav className="pagination" aria-label={label}>
    <button type="button" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}><AppIcon name="arrow" className="previous-arrow" />Anterior</button>
    <span aria-live="polite" aria-atomic="true">Página {meta.current_page} de {meta.last_page} · {meta.total} registros</span>
    <button type="button" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)}>Siguiente<AppIcon name="arrow" /></button>
  </nav>
}
