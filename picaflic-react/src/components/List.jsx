export function List({ title, items }) {
  return (
    <>
      <div className="card"><b>{title}</b><div className="muted">{items.length} item(s)</div></div>
      <div className="grid">
        {items.map(m=>(
          <div key={m.id} className="card">
            <div><b>{m.title}</b></div>
            <div className="muted">id {m.id} • tmdb {m.tmdbId}</div>
          </div>
        ))}
      </div>
    </>
  )
}
