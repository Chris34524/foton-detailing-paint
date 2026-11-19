import { formatMxn } from './formatMxn'

export default function InventoryValuationSummary({ summary }) {
  if (!summary) return null

  return <section className="inventory-valuation-summary" aria-label="Costo total del inventario">
    <div>
      <p className="eyebrow">VALORIZACIÓN ACTUAL</p>
      <h2>Costo total del inventario</h2>
      <p className="muted">Suma del stock actual × costo unitario de todos los productos que cumplen los filtros, incluidos los de otras páginas.</p>
    </div>
    <div className="inventory-valuation-total">
      <strong>{formatMxn(summary.total_cost_mxn)}</strong>
      <span>{summary.material_count} productos encontrados</span>
    </div>
    {summary.unpriced_with_stock_count > 0 && <p className="inventory-valuation-warning">
      {summary.unpriced_with_stock_count} producto(s) con existencias no tienen costo configurado.
      El importe mostrado es parcial hasta completar esos costos.
    </p>}
  </section>
}
