import { useCallback, useState } from 'react'
import useData from '../diagnosis/useDiagnosisData'
import Pagination from '../diagnosis/Pagination'
import operationsService from './operationsService'
import { LoadState } from './Feedback'
export default function CatalogPicker({ kind, label, value, onChange, selected = null, activeOnly = true }) {
  const [search, setSearch] = useState('')
  const [filters, setFilters] = useState({ page: 1 })
  const loader = useCallback((signal) => kind === 'orders'
    ? operationsService.eligibleOrders(filters, { signal })
    : operationsService.catalog(kind, { ...filters, ...(activeOnly ? { active: 1 } : {}) }, { signal }), [kind, filters, activeOnly])
  const resource = useData('picker:' + kind + ':' + JSON.stringify(filters), loader)
  const prefix = 'picker-' + kind
  function display(record) { return kind === 'orders' ? '#' + record.id + ' · ' + record.vin + ' · ' + (record.model || 'Sin modelo') : (record.code ? record.code + ' · ' : '') + record.name }
  return <div className="catalog-picker">
    <label htmlFor={prefix + '-search'}>Buscar {label.toLowerCase()}</label>
    <div className="picker-search"><input id={prefix + '-search'} value={search} maxLength={255} onChange={(event) => setSearch(event.target.value)} />
      <button type="button" onClick={() => setFilters({ page: 1, ...(search.trim() ? { search: search.trim() } : {}) })}>Buscar opciones</button></div>
    <LoadState resource={resource}>{resource.data && <>
      {resource.data.data.length === 0 && <p className="empty-state">No hay opciones activas disponibles. Revisa la búsqueda o solicita su configuración.</p>}
      <label htmlFor={prefix}>{label}</label>
      <select id={prefix} value={value || ''} onChange={(event) => onChange(event.target.value, resource.data.data.find((record) => String(record.id) === event.target.value) || selected)}>
        <option value="">Selecciona una opción</option>
        {selected && !resource.data.data.some((record) => record.id === selected.id) && <option value={selected.id}>{display(selected)}</option>}
        {resource.data.data.map((record) => <option key={record.id} value={record.id}>{display(record)}</option>)}
      </select>
      <Pagination meta={resource.data.meta} label={'Paginación de ' + label.toLowerCase()} onPage={(page) => setFilters((current) => ({ ...current, page }))} />
    </>}</LoadState>
  </div>
}
