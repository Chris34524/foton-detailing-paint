export default function UnitStatusBadge({ unit }) {
  return <span className="status-badge" data-status={unit.status}>{unit.status_label}</span>
}