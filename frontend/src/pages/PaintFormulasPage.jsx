import { useCallback, useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import useAuth from '../auth/useAuth'
import operationsService from '../operations/operationsService'
import paintService from '../operations/paintService'
import { formatQuantity } from '../operations/formatQuantity'
import { operationsPermissions } from '../operations/permissions'
import InventoryNavigation from '../operations/InventoryNavigation'

const blank = () => ({ vehicle_model: '', color_name: '', color_code: '', components: [{ material_id: '', grams: '' }] })
const validGrams = (value) => /^\d{1,12}(?:\.\d{1,2})?$/.test(value) && Number(value) > 0
function errorMessage(error, fallback) {
  const fields = error?.response?.data?.errors
  if (fields) return Object.values(fields).flat().join(' ')
  return error?.response?.data?.message || fallback
}

export default function PaintFormulasPage() {
  const { can } = useAuth()
  const canManage = can(operationsPermissions.materialsManage)
  const [formulas, setFormulas] = useState([])
  const [ingredients, setIngredients] = useState([])
  const [history, setHistory] = useState([])
  const [selected, setSelected] = useState(null)
  const [editor, setEditor] = useState(null)
  const [draft, setDraft] = useState(blank())
  const [query, setQuery] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [grams, setGrams] = useState('')
  const [area, setArea] = useState('')
  const [notes, setNotes] = useState('')
  const [preview, setPreview] = useState(null)
  const [previewError, setPreviewError] = useState('')
  const [receipt, setReceipt] = useState(null)
  const retryKey = useRef(null)

  const reload = useCallback(async (signal) => {
    const [formulasResult, historyResult] = await Promise.all([
      paintService.list({ page, ...(search ? { search } : {}) }, { signal }),
      paintService.preparations({ per_page: 10 }, { signal }),
    ])
    setFormulas(formulasResult.data)
    setPagination(formulasResult.meta)
    setHistory(historyResult.data)
  }, [page, search])

  useEffect(() => {
    const controller = new AbortController()
    setLoading(true)
    reload(controller.signal).catch((failure) => {
      if (!controller.signal.aborted) setError(errorMessage(failure, 'No se pudieron consultar las formulaciones.'))
    }).finally(() => { if (!controller.signal.aborted) setLoading(false) })
    return () => controller.abort()
  }, [reload])

  useEffect(() => {
    if (!canManage) return undefined
    const controller = new AbortController()
    const collect = async () => {
      let next = 1
      let end = 1
      const items = []
      do {
        const result = await operationsService.catalog('materials', {
          page: next, per_page: 100, active: 1, sort: 'name',
        }, { signal: controller.signal })
        items.push(...result.data.filter((material) =>
          material.kind !== 'finished_paint' && ['Kg', 'gr'].includes(material.unit)))
        end = Number(result.meta?.last_page || 1)
        next += 1
      } while (next <= end)
      if (!controller.signal.aborted) setIngredients(items)
    }
    collect().catch((failure) => {
      if (!controller.signal.aborted) setError(errorMessage(failure, 'No se pudo cargar el catálogo de materias primas.'))
    })
    return () => controller.abort()
  }, [canManage])

  useEffect(() => {
    setPreview(null)
    setPreviewError('')
    if (!selected?.is_current || !validGrams(grams)) return undefined
    const controller = new AbortController()
    paintService.preview(selected.id, grams, { signal: controller.signal })
      .then((result) => { if (!controller.signal.aborted) setPreview(result) })
      .catch((failure) => { if (!controller.signal.aborted) setPreviewError(errorMessage(failure, 'No se pudo calcular la preparación.')) })
    return () => controller.abort()
  }, [selected, grams])

  function openEditor(formula = null) {
    setEditor(formula ? { id: formula.id } : { id: null })
    setDraft(formula ? {
      vehicle_model: formula.vehicle_model,
      color_name: formula.color_name,
      color_code: formula.color_code,
      components: formula.components.map((item) => ({ material_id: String(item.material_id), grams: formatQuantity(item.grams) })),
    } : blank())
    setError('')
    setMessage('')
  }

  async function saveFormula(event) {
    event.preventDefault()
    if (busy) return
    const components = draft.components.map((item) => ({ material_id: Number(item.material_id), grams: item.grams }))
    if (!components.length || components.some((item) => !item.material_id || !validGrams(item.grams))) {
      setError('Selecciona componentes y captura gramajes positivos con dos decimales como máximo.')
      return
    }
    setBusy(true)
    setError('')
    try {
      const saved = editor.id ? await paintService.revise(editor.id, { ...draft, components })
        : await paintService.create({ ...draft, components })
      setEditor(null)
      setSelected(saved)
      setGrams('')
      setReceipt(null)
      setMessage('Fórmula guardada correctamente. Los materiales aún no se han descontado.')
      await reload()
    } catch (failure) {
      setError(errorMessage(failure, 'No fue posible guardar la fórmula.'))
    } finally {
      setBusy(false)
    }
  }

  function chooseFormula(formula) {
    setSelected(formula)
    setEditor(null)
    setGrams('')
    setPreview(null)
    setReceipt(null)
    setArea('')
    setNotes('')
    setError('')
    setMessage('')
    retryKey.current = null
  }

  async function prepare(event) {
    event.preventDefault()
    if (busy || !selected || !preview?.can_confirm) return
    setBusy(true)
    setError('')
    setMessage('')
    if (!retryKey.current) retryKey.current = crypto.randomUUID()
    try {
      const saved = await paintService.prepare(selected.id, {
        quantity_grams: grams,
        area_destination: area || null,
        notes: notes.trim() || null,
        idempotency_key: retryKey.current,
      })
      retryKey.current = null
      setReceipt(saved)
      setMessage('Preparación confirmada. Los materiales base se descontaron y la pintura terminada ingresó a inventario.')
      await reload()
      const updated = await paintService.get(selected.id)
      setSelected(updated)
    } catch (failure) {
      setError(errorMessage(failure, 'No fue posible confirmar la preparación. Puedes reintentar sin duplicar el descuento.'))
    } finally {
      setBusy(false)
    }
  }

  return <section className="workspace-card paint-page">
    <div className="section-heading">
      <div><p className="eyebrow">CONTROL DE INVENTARIO</p><h1>Formulación de Pintura</h1>
        <p className="muted page-description">Configura recetas por modelo y color. Prepara lotes con gramajes exactos y movimientos automáticos.</p>
      </div>
      {canManage && <button className="primary-button" onClick={() => openEditor()}>Nueva fórmula</button>}
    </div>
    <InventoryNavigation />
    {message && <p className="success-message" role="status">{message}</p>}
    {error && <p className="error" role="alert">{error}</p>}

    {editor && canManage && <form className="paint-editor" aria-label="Editor de fórmula" onSubmit={saveFormula}>
      <div className="section-heading"><h2>{editor.id ? 'Nueva versión de fórmula' : 'Crear fórmula de pintura'}</h2>
        <button type="button" onClick={() => setEditor(null)}>Cancelar</button></div>
      <p className="field-hint">La receta solo se registra; no descuenta inventario hasta confirmar la preparación. Cada material se registra en gramos.</p>
      <div className="paint-fields">
        <div><label htmlFor="paint-model">Modelo de vehículo</label><input id="paint-model" required maxLength={100}
          disabled={Boolean(editor.id)} value={draft.vehicle_model} placeholder="Ej. FOTON S5"
          onChange={(event) => setDraft((value) => ({ ...value, vehicle_model: event.target.value }))} /></div>
        <div><label htmlFor="paint-color">Nombre de color</label><input id="paint-color" required maxLength={160}
          value={draft.color_name} placeholder="Ej. Blanco perla"
          onChange={(event) => setDraft((value) => ({ ...value, color_name: event.target.value }))} /></div>
        <div><label htmlFor="paint-color-code">Código de color</label><input id="paint-color-code" required maxLength={60}
          disabled={Boolean(editor.id)} value={draft.color_code} placeholder="Ej. WP01"
          onChange={(event) => setDraft((value) => ({ ...value, color_code: event.target.value }))} /></div>
      </div>
      <div className="section-heading"><h3>Materias primas y gramajes</h3>
        <button type="button" disabled={draft.components.length >= 30} onClick={() => setDraft((value) => ({
          ...value, components: [...value.components, { material_id: '', grams: '' }],
        }))}>Agregar componente</button></div>
      {draft.components.map((item, index) => <div className="paint-ingredient-row" key={index}>
        <div><label htmlFor={'paint-ingredient-' + index}>Material base {index + 1}</label>
          <select id={'paint-ingredient-' + index} value={item.material_id} required onChange={(event) => setDraft((value) => ({
            ...value, components: value.components.map((row, i) => i === index ? { ...row, material_id: event.target.value } : row),
          }))}>
            <option value="">Seleccionar materia prima</option>
            {ingredients.map((material) => <option key={material.id} value={material.id}>{material.code} · {material.name} ({material.unit})</option>)}
          </select></div>
        <div><label htmlFor={'paint-grams-' + index}>Gramaje (g)</label>
          <input id={'paint-grams-' + index} type="text" inputMode="decimal" required pattern="[0-9]+([.][0-9]{1,2})?"
            placeholder="125.35" value={item.grams} onChange={(event) => setDraft((value) => ({
              ...value, components: value.components.map((row, i) => i === index ? { ...row, grams: event.target.value } : row),
            }))} /></div>
        <button type="button" aria-label={'Quitar componente ' + (index + 1)} disabled={draft.components.length === 1}
          onClick={() => setDraft((value) => ({ ...value, components: value.components.filter((_, i) => i !== index) }))}>Quitar</button>
      </div>)}
      <p className="paint-total">Total de la fórmula: <strong>{
        draft.components.reduce((sum, item) => sum + (validGrams(item.grams) ? Math.round(Number(item.grams) * 100) : 0), 0) / 100
      } g</strong></p>
      <button className="primary-button" disabled={busy}>{busy ? 'Guardando…' : 'Guardar fórmula'}</button>
    </form>}

    <div className="paint-workspace">
      <section className="paint-panel" aria-labelledby="paint-catalog-heading">
        <h2 id="paint-catalog-heading">Catálogo de formulaciones</h2>
        <form className="paint-search" onSubmit={(event) => { event.preventDefault(); setPage(1); setSearch(query.trim()) }}>
          <label htmlFor="paint-search">Buscar modelo, color o código</label>
          <div><input id="paint-search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Buscar fórmula" />
            <button>Buscar</button></div>
        </form>
        {loading && <p role="status">Cargando formulaciones…</p>}
        {!loading && formulas.length === 0 && <p className="empty-state">Todavía no hay fórmulas registradas.</p>}
        <div className="paint-formula-list">
          {formulas.map((formula) => <article className="paint-formula-item" key={formula.id}>
            <div><strong>{formula.color_name}</strong><span>{formula.vehicle_model} · {formula.color_code}</span>
              <small>{formula.product.code} · Versión {formula.version} · {formatQuantity(formula.total_grams)} g</small></div>
            <div className="paint-formula-actions">
              <button type="button" onClick={() => chooseFormula(formula)}>Ver / Preparar</button>
              {canManage && <button type="button" onClick={() => openEditor(formula)}>Nueva versión</button>}
            </div>
          </article>)}
        </div>
        {pagination?.last_page > 1 && <div className="form-actions">
          <button type="button" disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Anterior</button>
          <span>Página {page} de {pagination.last_page}</span>
          <button type="button" disabled={page >= pagination.last_page} onClick={() => setPage((value) => value + 1)}>Siguiente</button>
        </div>}
      </section>

      <section className="paint-panel" aria-labelledby="paint-detail-heading">
        <h2 id="paint-detail-heading">Detalle y preparación</h2>
        {!selected && <p className="empty-state">Selecciona una fórmula para consultar su composición y preparar pintura.</p>}
        {selected && <>
          <div className="paint-detail-title">
            <strong>{selected.color_name} · {selected.vehicle_model}</strong>
            <span>{selected.product.code} · Color {selected.color_code} · Versión {selected.version}</span>
            <span>Producto terminado disponible: {formatQuantity(selected.product.stock_actual)} g</span>
          </div>
          {!selected.is_current && <p role="alert">Esta versión es histórica y no admite preparaciones nuevas.</p>}
          <div className="table-scroll" tabIndex={0} role="region" aria-label="Composición de pintura">
            <table><thead><tr><th>Código</th><th>Materia prima</th><th>Gramaje</th><th>Existencia</th></tr></thead>
              <tbody>{selected.components.map((item) => <tr key={item.material_id}>
                <td>{item.code}</td><td>{item.name}</td><td>{formatQuantity(item.grams)} g</td><td>{formatQuantity(item.stock_actual)} {item.unit}</td>
              </tr>)}</tbody></table>
          </div>
          <p className="paint-total">Fórmula base: <strong>{formatQuantity(selected.total_grams)} g</strong></p>
          {canManage && selected.is_current && <form className="paint-prepare" onSubmit={prepare}>
            <h3>Preparar lote</h3>
            <div className="paint-fields">
              <div><label htmlFor="paint-batch-grams">Cantidad a preparar (g)</label>
                <input id="paint-batch-grams" required type="text" inputMode="decimal" pattern="[0-9]+([.][0-9]{1,2})?"
                  value={grams} placeholder="Ej. 1000.00" onChange={(event) => {
                    retryKey.current = null; setGrams(event.target.value); setReceipt(null)
                  }} /></div>
              <div><label htmlFor="paint-batch-area">Área / destino</label><select id="paint-batch-area" value={area}
                onChange={(event) => { retryKey.current = null; setArea(event.target.value) }}>
                <option value="">Sin destino específico</option><option>Pintura 1</option><option>Pintura 2</option><option>Lavado</option>
              </select></div>
            </div>
            <label htmlFor="paint-batch-notes">Observaciones</label>
            <textarea id="paint-batch-notes" maxLength={5000} value={notes}
              onChange={(event) => { retryKey.current = null; setNotes(event.target.value) }} />
            {previewError && <p role="alert">{previewError}</p>}
            {preview && <div className="paint-preview">
              <h3>Consumo calculado para {formatQuantity(preview.quantity_grams)} g</h3>
              {preview.components.map((row) => <div className="paint-preview-row" key={row.material_id}>
                <span>{row.name}</span><strong>{formatQuantity(row.grams)} g</strong>
                <span className={row.sufficient ? 'paint-available' : 'paint-insufficient'}>
                  Disponible: {formatQuantity(row.available_quantity)} {row.unit} {row.sufficient ? '✓' : '· Insuficiente'}
                </span>
              </div>)}
              <p>Entrada del producto terminado: <strong>{formatQuantity(preview.quantity_grams)} g</strong></p>
            </div>}
            <button className="primary-button" disabled={busy || !preview?.can_confirm}>
              {busy ? 'Confirmando…' : 'Confirmar preparación y actualizar inventario'}
            </button>
            <p className="field-hint">La operación crea movimientos automáticos de salida y entrada. No se permiten saldos negativos.</p>
          </form>}
          {receipt && <div className="success-message" aria-label="Comprobante de preparación">
            <h3>Lote preparado #{receipt.id}</h3>
            <p>{formatQuantity(receipt.quantity_grams)} g · Entrada <strong>{receipt.output_folio}</strong></p>
            <ul>{receipt.components.map((item) => <li key={item.material_id}>{item.name}: {formatQuantity(item.grams)} g · {item.movement_folio}</li>)}</ul>
            <Link to="/materials/movements">Consultar movimientos</Link>
          </div>}
        </>}
      </section>
    </div>

    <section className="paint-panel" aria-labelledby="paint-history-heading">
      <h2 id="paint-history-heading">Preparaciones recientes</h2>
      {history.length === 0 && <p className="empty-state">Aún no se han preparado lotes.</p>}
      {history.length > 0 && <div className="table-scroll" tabIndex={0} role="region" aria-label="Historial de preparaciones">
        <table><thead><tr><th>Lote</th><th>Modelo</th><th>Color</th><th>Versión</th><th>Preparado</th><th>Entrada</th><th>Responsable</th></tr></thead>
          <tbody>{history.map((item) => <tr key={item.id}><td>#{item.id}</td><td>{item.vehicle_model}</td>
            <td>{item.color_name}</td><td>{item.version}</td><td>{formatQuantity(item.quantity_grams)} g</td>
            <td>{item.output_folio}</td><td>{item.prepared_by || 'Usuario no disponible'}</td></tr>)}</tbody></table>
      </div>}
    </section>
  </section>
}
