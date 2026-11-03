import { useCallback } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import useData from '../diagnosis/useDiagnosisData'
import Pagination from '../diagnosis/Pagination'
import operationsService from '../operations/operationsService'
import { LoadState } from '../operations/Feedback'
import ConsumptionTable from '../operations/ConsumptionTable'
export default function MaterialConsumptionsPage() {
  const [params, setParams] = useSearchParams()
  const query = params.toString()
  const loader = useCallback((signal) => operationsService.consumptions(Object.fromEntries(new URLSearchParams(query)), null, { signal }), [query])
  const resource = useData('material-consumptions:' + query, loader)
  function search(event) {
    event.preventDefault()
    const fields = new FormData(event.currentTarget)
    const next = new URLSearchParams()
    for (const [key, value] of fields) if (value.trim()) next.set(key, value.trim())
    setParams(next)
  }
  return <section className="workspace-card"><Link to="/materials">← Materiales</Link><h1>Consumos de materiales</h1>
    <form key={query} className="diagnosis-search" onSubmit={search}>
      <div><label htmlFor="consumption-vin">VIN exacto</label><input id="consumption-vin" name="vin" maxLength={17} defaultValue={params.get('vin') || ''} /></div>
      <div><label htmlFor="consumption-search">Buscar consumos</label><input id="consumption-search" name="search" maxLength={255} defaultValue={params.get('search') || ''} /></div>
      <button>Buscar</button><button type="button" onClick={() => setParams({})}>Limpiar filtros</button>
    </form>
    <LoadState resource={resource}>{resource.data && <>
      <ConsumptionTable data={resource.data.data} />
      <Pagination meta={resource.data.meta} label="Paginación de consumos" onPage={(page) => { const next = new URLSearchParams(params); next.set('page', page); setParams(next) }} />
    </>}</LoadState>
    <p className="muted">Registra los consumos desde el detalle de una orden activa.</p>
  </section>
}
