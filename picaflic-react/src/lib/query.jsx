import { QueryClient, QueryClientProvider } from '@tanstack/react-query'

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 30,   // 30s
      refetchOnWindowFocus: false,
      retry: 1,
    },
  },
})

export function WithQueryClient({ children }) {
  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
}
