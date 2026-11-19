const paths = {
  dashboard: 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
  units: 'M3 7h12v10H3zM15 10h3l3 3v4h-6M7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4M18 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4',
  diagnosis: 'M9 5H5v16h14V5h-4M9 3h6v4H9zM8 12h8M8 16h5',
  work: 'M4 7h16v14H4zM8 7V3h8v4M4 12h16M10 11v3h4v-3',
  process: 'M4 4h6v6H4zM14 14h6v6h-6zM10 7h7v7M7 10v7h7',
  material: 'm12 3 9 5-9 5-9-5 9-5ZM3 8v9l9 5 9-5V8M12 13v9M7.5 5.5l9 5',
  inspection: 'm12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3ZM8 12l3 3 5-6',
  arrow: 'M5 12h14m-6-6 6 6-6 6',
  menu: 'M4 6h16M4 12h16M4 18h16',
  close: 'm6 6 12 12M6 18 18 6',
  logout: 'M9 4H4v16h5M9 12h12m-5-5 5 5-5 5',
  eye: 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12ZM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6',
  check: 'm5 12 4 4L19 6',
  search: 'M10 3a7 7 0 1 0 0 14 7 7 0 0 0 0-14Zm5 12 6 6',
}
export default function AppIcon({ name, className = '' }) {
  return <svg className={'app-icon ' + className} width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false"><path d={paths[name] || paths.dashboard} /></svg>
}
