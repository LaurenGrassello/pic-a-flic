import { useState } from 'react'
import { api } from '../api'

export default function Matches(){
  const [friendId, setFriendId] = useState('')
  const [items, setItems] = useState([])
  const [msg, setMsg] = useState('')

  async function fetchMatches(){
    try{
      if(!friendId) throw new Error('Enter friend user id')
      const data = await api.matches(friendId, {})
      setItems(data.results || []); setMsg('')
    }catch(e){ setMsg(e.message) }
  }

  return (
    <>
      <div className="card">
        <div className="row">
          <input value={friendId} onChange={e=>setFriendId(e.target.value)} placeholder="friend user id" />
          <button onClick={fetchMatches}>See Matches</button>
          {msg && <span className="muted err">{msg}</span>}
        </div>
      </div>
      <div className="card"><b>Matches</b><div className="muted">{items.length} item(s)</div></div>
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
