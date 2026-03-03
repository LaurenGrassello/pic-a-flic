import { img } from '../api'

export default function PosterCard({ movie, children }) {
  return (
    <div className="card">
      {movie.posterPath && (
        <img
          src={img.poster(movie.posterPath, 342)}
          alt={movie.title}
          style={{ width: '100%', borderRadius: 8, marginBottom: 8 }}
        />
      )}
      <div><b>{movie.title}</b>{movie.releaseYear ? ` (${movie.releaseYear})` : ''}</div>
      <div className="muted">id {movie.id} • tmdb {movie.tmdbId}</div>
      {children}
    </div>
  )
}
