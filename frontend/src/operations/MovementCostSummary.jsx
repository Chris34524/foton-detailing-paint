import { formatMxn } from './formatMxn'

export default function MovementCostSummary({ summary, dateFrom, dateTo }) {
  if (!summary) return null
  const period = dateFrom || dateTo
    ? 'Periodo: ' + (dateFrom || 'Inicio') + ' al ' + (dateTo || 'hoy')
    : 'Periodo: todos los movimientos'

  return <section className="movement-cost-summary" aria-label="Resumen de costos del periodo">
    <div className="movement-cost-heading">
      <div><h2>Costos de movimientos</h2><p>{period} · Todos los registros que cumplen los filtros, no solo esta página</p></div>
      <span>{summary.movement_count} movimientos</span>
    </div>
    <dl className="movement-cost-grid">
      <div><dt>Entradas y ajustes positivos</dt><dd>{formatMxn(summary.inbound_total_mxn, { maximumFractionDigits: 6 })}</dd></div>
      <div><dt>Salidas y ajustes negativos</dt><dd>{formatMxn(summary.outbound_total_mxn, { maximumFractionDigits: 6 })}</dd></div>
      <div><dt>Costo total de movimientos con precio</dt><dd>{formatMxn(summary.recorded_total_mxn, { maximumFractionDigits: 6 })}</dd></div>
    </dl>
    <p className="movement-cost-note">
      {summary.with_cost_count} movimientos valorizados · {summary.without_cost_count} sin costo registrado.
      {summary.without_cost_count > 0 && ' Los importes son parciales: falta configurar el costo de algunos productos.'}
      {' '}Estos importes representan valor de materiales movidos, no utilidad ni gasto contable.
    </p>
  </section>
}
