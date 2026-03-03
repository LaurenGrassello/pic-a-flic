import { api } from '../api'

export default function Deck({ items, reload }) {
  async function swipe(id, liked){
    try {
      await api.swipe(id, liked)
      reload?.()
    } catch (e) {
      alert(e.message)
    }
  }
  return (
    <>
      <div className="card"><b>Swipe Deck</b><div className="muted">{items.length} item(s)</div></div>
      <div className="grid">
        {items.map(m=>(
          <div key={m.id} className="card">
            <div><b>{m.title}</b></div>
            <div className="muted">id {m.id} • tmdb {m.tmdbId}</div>
            <div className="row" style={{marginTop:8}}>
              <button onClick={()=>swipe(m.id, false)}>👎 Skip</button>
              <button onClick={()=>swipe(m.id, true)}>👍 Like</button>
            </div>
          </div>
        ))}
      </div>
    </>
  )
}
