import { useCallback, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import useAuth from '../auth/useAuth'
import useData from '../diagnosis/useDiagnosisData'
import Pagination from '../diagnosis/Pagination'
import operationsService from '../operations/operationsService'
import inventoryService from '../operations/inventoryService'
import InventoryNavigation from '../operations/InventoryNavigation'
import InventoryTable from '../operations/InventoryTable'
import useInventoryRefresh from '../operations/useInventoryRefresh'
import { operationsPermissions as permissions } from '../operations/permissions'
import { LoadState } from '../operations/Feedback'
import CatalogForm from '../operations/CatalogForm'
import SequencePanel from '../operations/SequencePanel'
import ChecklistItems from '../operations/ChecklistItems'
const configs = {
  processes: { title: 'Procesos', description: 'Catálogo y secuencia de los trabajos de detallado y pintura.', manage: permissions.processesManage },
  materials: { title: 'Materiales', description: 'Existencias, abastecimiento y movimientos de los insumos de la operación.', manage: permissions.materialsManage },
  checklists: { title: 'Checklists de inspección', description: 'Criterios de calidad que se aplican a los trabajos terminados.', manage: permissions.inspectionManage },
}
export default function OperationsCatalogPage({ kind }) {
  const config = configs[kind]
  const { can } = useAuth()
  const [params, setParams] = useSearchParams()
  const query = params.toString()
  const [editor, setEditor] = useState(null)
  const [notice, setNotice] = useState('')
  const [itemsFor, setItemsFor] = useState(null)
  const loader = useCallback((signal) => operationsService.catalog(kind, Object.fromEntries(new URLSearchParams(query)), { signal }), [kind, query])
  const resource = useData('catalog:' + kind + ':' + query, loader)
  const metaLoader = useCallback((signal) => kind === 'materials' ? inventoryService.meta({ signal }) : Promise.resolve(null), [kind])
  const metadata = useData('catalog-meta:' + kind, metaLoader)
  useInventoryRefresh(kind === 'materials' ? resource.retry : null)
  function search(event) {
    event.preventDefault()
    const fields = new FormData(event.currentTarget)
    const next = new URLSearchParams()
    for (const [key, value] of fields) if (value.trim()) next.set(key, value.trim())
    setParams(next)
  }
  function page(value) { const next = new URLSearchParams(params); next.set('page', value); setParams(next) }
  return <section className="workspace-card">
    <div className="section-heading"><div><p className="eyebrow">{kind === "materials" ? "CONTROL DE INVENTARIO" : "CONFIGURACIÓN"}</p><h1>{config.title}</h1><p className="muted page-description">{config.description}</p></div>
      {can(config.manage) && <button className="primary-button" onClick={() => { setNotice(''); setEditor({ record: null }) }}>{kind === 'materials' ? 'Nuevo producto' : 'Nuevo registro'}</button>}</div>
    {kind === 'materials' && <><InventoryNavigation /><LoadState resource={metadata}>{null}</LoadState></>}
    {kind === 'checklists' && <p><Link to="/inspections">Volver a Inspección</Link></p>}
    <form key={query} className="diagnosis-search" onSubmit={search}>
      <div><label htmlFor="catalog-search">Buscar en catálogo</label><input id="catalog-search" name="search" maxLength={255} defaultValue={params.get('search') || ''} /></div>
      <div><label htmlFor="catalog-active">Actividad</label><select id="catalog-active" name="active" defaultValue={params.get('active') || ''}><option value="">Todos</option><option value="1">Activos</option><option value="0">Inactivos</option></select></div>
      {kind === 'materials' && metadata.data && <div><label htmlFor="catalog-unit">Unidad de medida</label><select id="catalog-unit" name="unit" defaultValue={params.get('unit') || ''}><option value="">Todas</option>{metadata.data.units.map((unit) => <option key={unit.value} value={unit.value}>{unit.label}</option>)}</select></div>}
      {kind === 'materials' && metadata.data && <div><label htmlFor="inventory-status">Estado de inventario</label><select id="inventory-status" name="stock_status" defaultValue={params.get('stock_status') || ''}>
        <option value="">Todos</option>{metadata.data.stock_statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
      </select></div>}
      <button>Buscar</button><button type="button" onClick={() => setParams({})}>Limpiar filtros</button>
    </form>
    {editor && (kind !== 'materials' || metadata.data) && <CatalogForm materialMeta={metadata.data} key={kind + ':' + (editor.record?.id ?? 'new')} kind={kind} record={editor.record} onCancel={() => setEditor(null)}
      onSaved={() => { setEditor(null); setNotice('Registro guardado correctamente.'); resource.retry() }} />}
    {notice && <p className="success-message" role="status">{notice}</p>}
    <LoadState resource={resource}>{resource.data && <>
      {resource.data.data.length === 0 ? <p className="empty-state">No hay registros configurados con estos criterios.</p>
        : kind === 'materials' ? <InventoryTable data={resource.data.data} canManage={can(config.manage)} canMove={can(permissions.materialsManage) || can(permissions.materialsConsume)} onEdit={(record) => { setNotice(''); setEditor({ record }) }} />
        : <div className="table-scroll" tabIndex={0} role="region" aria-label="Tabla de registros"><table><caption className="sr-only">{config.title}</caption><thead><tr>
          {kind !== 'checklists' && <th>Código</th>}<th>Nombre</th>{kind === 'materials' && <th>Unidad</th>}<th>Actividad</th><th>Acciones</th>
        </tr></thead><tbody>{resource.data.data.map((record) => <tr key={record.id}>
          {kind !== 'checklists' && <td>{record.code}</td>}<td>{record.name}</td>{kind === 'materials' && <td>{record.unit}</td>}
          <td>{record.is_active ? 'Activo' : 'Inactivo'}</td><td>{can(config.manage) && <>
            <button onClick={() => { setNotice(''); setEditor({ record }) }} aria-label={'Editar ' + record.name}>Editar</button>
            {kind === 'checklists' && <button onClick={() => setItemsFor(record)} aria-label={'Criterios de ' + record.name}>Criterios</button>}
          </>}</td></tr>)}</tbody></table></div>}
      <Pagination meta={resource.data.meta} onPage={page} label="Paginación del catálogo" />
    </>}</LoadState>
    {kind === 'processes' && <SequencePanel />}
    {kind === 'checklists' && itemsFor && can(config.manage) && <ChecklistItems key={itemsFor.id} checklistId={itemsFor.id} />}
  </section>
}
