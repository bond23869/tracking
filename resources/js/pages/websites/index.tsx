import { Head, usePage } from '@inertiajs/react'
import AppLayout from '@/layouts/app-layout'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { formatDistanceToNow } from 'date-fns'
import { Globe } from 'lucide-react'
import { Icon } from '@/components/icon'

interface Website {
  id: number
  name: string
  url: string
  status: string
  connection_status: string
  connection_error?: string | null
  created_at: string
  updated_at: string
}

interface WebsitePageProps {
  websites: Website[]
  currentAccount?: {
    id: number
    name: string
    slug: string
  } | null
  errors?: Record<string, string>
  success?: string
}

export default function WebsitesIndex() {
  const { websites, currentAccount } = usePage<WebsitePageProps>().props

  const getStatusBadgeVariant = (status: string) => {
    switch (status) {
      case 'active':
        return 'default'
      case 'inactive':
        return 'secondary'
      default:
        return 'outline'
    }
  }

  const getConnectionStatusBadgeVariant = (connectionStatus: string) => {
    switch (connectionStatus) {
      case 'connected':
        return 'default'
      case 'disconnected':
        return 'destructive'
      case 'error':
        return 'destructive'
      default:
        return 'secondary'
    }
  }

  return (
    <AppLayout>
      <Head title="Websites" />
      
      <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        {!currentAccount ? (
          <div className="space-y-6">
            <div>
              <h1 className="text-3xl font-bold tracking-tight">Websites</h1>
              <p className="text-muted-foreground">
                Manage your websites
              </p>
            </div>
            <Card>
              <CardHeader>
                <CardTitle>Websites</CardTitle>
                <CardDescription>
                  Websites for the selected account
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="flex flex-col items-center justify-center py-12 text-center">
                  <div className="rounded-full bg-muted p-3 mb-4">
                    <Icon iconNode={Globe} className="h-6 w-6 text-muted-foreground" />
                  </div>
                  <h3 className="text-lg font-medium mb-2">No account selected</h3>
                  <p className="text-muted-foreground">
                    Please select an account from the sidebar to view its websites.
                  </p>
                </div>
              </CardContent>
            </Card>
          </div>
        ) : websites.length === 0 ? (
          <div className="flex h-full items-center justify-center">
            <div className="flex flex-col items-center justify-center text-center space-y-4">
              <h2 className="text-2xl font-semibold tracking-tight">No websites</h2>
              <p className="text-muted-foreground max-w-md">
                Looks like you haven't added any websites yet. Get started by adding your first one.
              </p>
              <Button asChild className="mt-2">
                <a href="#">Add a new website</a>
              </Button>
            </div>
          </div>
        ) : (
          <div className="space-y-6">
            <div>
              <h1 className="text-3xl font-bold tracking-tight">Websites</h1>
              <p className="text-muted-foreground">
                Manage websites for {currentAccount.name}
              </p>
            </div>

            {/* Websites List */}
            <Card>
              <CardHeader>
                <CardTitle>Websites ({websites.length})</CardTitle>
                <CardDescription>
                  All websites under {currentAccount.name}
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="rounded-md border">
                  <table className="w-full">
                    <thead>
                      <tr className="border-b bg-muted/50">
                        <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                          Website
                        </th>
                        <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                          Status
                        </th>
                        <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                          Connection
                        </th>
                        <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                          Created
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      {websites.map((website) => (
                        <tr key={website.id} className="border-b transition-colors hover:bg-muted/50">
                          <td className="p-4 align-middle">
                            <div className="font-medium">{website.name}</div>
                            <div className="text-sm text-muted-foreground">
                              <a 
                                href={website.url} 
                                target="_blank" 
                                rel="noopener noreferrer"
                                className="text-blue-600 hover:underline dark:text-blue-400"
                              >
                                {website.url}
                              </a>
                            </div>
                            {website.connection_error && (
                              <div className="mt-1 text-xs text-red-600 dark:text-red-400">
                                {website.connection_error}
                              </div>
                            )}
                          </td>
                          <td className="p-4 align-middle">
                            <Badge variant={getStatusBadgeVariant(website.status)}>
                              {website.status}
                            </Badge>
                          </td>
                          <td className="p-4 align-middle">
                            <Badge variant={getConnectionStatusBadgeVariant(website.connection_status)}>
                              {website.connection_status}
                            </Badge>
                          </td>
                          <td className="p-4 align-middle">
                            <div className="text-sm">
                              {formatDistanceToNow(new Date(website.created_at), { addSuffix: true })}
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </CardContent>
            </Card>
          </div>
        )}
      </div>
    </AppLayout>
  )
}
