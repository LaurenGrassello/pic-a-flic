import { useQuery, useInfiniteQuery } from '@tanstack/react-query'
import { api } from '../api'

export function useForYou(params) {
  return useQuery({
    queryKey: ['forYou', params],
    queryFn: () => api.forYou({ limit: 60, page: 1, providers: 'netflix|hulu|disney|prime|max|appletv|peacock' }),
  })
}

export function useChallenge(params) {
  return useQuery({
    queryKey: ['challenge', params],
    queryFn: () => api.challenge(params),
  })
}

// infinite pagination for deck (cursor = last id)
export function useDeckInfinite(params) {
  return useInfiniteQuery({
    queryKey: ['deck', params],
    queryFn: async ({ pageParam }) => {
      const q = { ...params, ...(pageParam ? { after: pageParam } : {}) }
      return api.deck(q)
    },
    getNextPageParam: (lastPage) => {
      const items = lastPage?.results ?? []
      if (items.length === 0) return undefined
      return items[items.length - 1].id // next "after"
    },
  })
}
