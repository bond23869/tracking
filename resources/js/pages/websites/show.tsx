import { Head, usePage, Link } from '@inertiajs/react'
import AppLayout from '@/layouts/app-layout'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { formatDistanceToNow } from 'date-fns'
import { Globe, ArrowLeft } from 'lucide-react'
import { Icon } from '@/components/icon'
import { cn } from '@/lib/utils'
import WooCommerceIcon from '@/components/icons/woocommerce-icon'
import ShopifyIcon from '@/components/icons/shopify-icon'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Archive, ArchiveRestore, MoreVertical } from 'lucide-react'
import { router } from '@inertiajs/react'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'
import { useState, useEffect } from 'react'

interface Website {
  id: number
  name: string
  url: string
  type: string
  status: string
  connection_status: string
  connection_error?: string | null
  archived_at?: string | null
  is_archived: boolean
  created_at: string
  updated_at: string
}

interface WebsiteShowProps {
  website: Website
  [key: string]: unknown
}

export default function WebsiteShow() {
  const { website: initialWebsite } = usePage<WebsiteShowProps>().props
  const [website, setWebsite] = useState(initialWebsite)

  // Update state when props change (e.g., after refresh)
  useEffect(() => {
    setWebsite(initialWebsite)
  }, [initialWebsite])

  const handleArchive = () => {
    router.post(`/websites/${website.id}/archive`, {}, {
      preserveScroll: false,
    })
  }

  const handleUnarchive = () => {
    router.post(`/websites/${website.id}/unarchive`, {}, {
      preserveScroll: false,
    })
  }

  const handleStatusToggle = (checked: boolean) => {
    const newStatus = checked ? 'active' : 'inactive'
    setWebsite({ ...website, status: newStatus })
    
    router.patch(`/websites/${website.id}`, { status: newStatus }, {
      preserveScroll: true,
      onError: () => {
        // Revert on error
        setWebsite(initialWebsite)
      }
    })
  }

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
      <Head title={`${website.name} - Websites`} />
      
      <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div className="space-y-6">
          {/* Header */}
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <Link href="/websites">
                <Button variant="ghost" size="icon">
                  <Icon iconNode={ArrowLeft} className="h-4 w-4" />
                  <span className="sr-only">Back to websites</span>
                </Button>
              </Link>
              <div className="flex items-center gap-3">
                {website.type === 'woocommerce' ? (
                  <WooCommerceIcon className="h-8 w-8" />
                ) : (
                  <ShopifyIcon className="h-8 w-8" />
                )}
                <div>
                  <h1 className={cn(
                    "text-3xl font-bold tracking-tight",
                    website.is_archived && "line-through text-muted-foreground"
                  )}>
                    {website.name}
                  </h1>
                  <p className="text-muted-foreground">
                    {website.type === 'woocommerce' ? 'WooCommerce' : 'Shopify'} Website
                  </p>
                </div>
              </div>
            </div>
            
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="h-8 w-8">
                  <Icon iconNode={MoreVertical} className="h-4 w-4" />
                  <span className="sr-only">Open menu</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                {website.is_archived ? (
                  <DropdownMenuItem onClick={handleUnarchive}>
                    <Icon iconNode={ArchiveRestore} className="mr-2 h-4 w-4" />
                    Unarchive
                  </DropdownMenuItem>
                ) : (
                  <DropdownMenuItem 
                    onClick={handleArchive}
                    variant="destructive"
                  >
                    <Icon iconNode={Archive} className="mr-2 h-4 w-4" />
                    Archive
                  </DropdownMenuItem>
                )}
              </DropdownMenuContent>
            </DropdownMenu>
          </div>

          {/* Website Details */}
          <Card>
            <CardHeader>
              <CardTitle>Website Details</CardTitle>
              <CardDescription>
                Information about this website
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {website.connection_error && (
                <div className="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/20 p-3 rounded border border-red-200 dark:border-red-800">
                  <div className="font-semibold mb-1">Connection Error</div>
                  {website.connection_error}
                </div>
              )}
              
              <div className="grid gap-4 md:grid-cols-2">
                <div>
                  <label className="text-sm font-medium text-muted-foreground">Name</label>
                  <p className={cn(
                    "text-sm",
                    website.is_archived && "line-through text-muted-foreground"
                  )}>
                    {website.name}
                  </p>
                </div>
                
                <div>
                  <label className="text-sm font-medium text-muted-foreground">URL</label>
                  <p className="text-sm">
                    <a 
                      href={website.url} 
                      target="_blank" 
                      rel="noopener noreferrer"
                      className={cn(
                        "text-blue-600 hover:underline dark:text-blue-400",
                        website.is_archived && "line-through"
                      )}
                    >
                      {website.url}
                    </a>
                  </p>
                </div>
                
                <div>
                  <label className="text-sm font-medium text-muted-foreground">Type</label>
                  <p className="text-sm">
                    {website.type === 'woocommerce' ? 'WooCommerce' : 'Shopify'}
                  </p>
                </div>
                
                <div>
                  <Label htmlFor="status-toggle" className="text-sm font-medium text-muted-foreground">
                    Status
                  </Label>
                  <div className="mt-2 flex items-center gap-2">
                    <Switch
                      id="status-toggle"
                      checked={website.status === 'active'}
                      onCheckedChange={handleStatusToggle}
                      disabled={website.is_archived}
                    />
                    <span className="text-sm text-muted-foreground">
                      {website.status === 'active' ? 'Active' : 'Inactive'}
                    </span>
                  </div>
                </div>
                
                <div>
                  <label className="text-sm font-medium text-muted-foreground">Connection Status</label>
                  <div className="mt-1">
                    <Badge variant={getConnectionStatusBadgeVariant(website.connection_status)}>
                      {website.connection_status}
                    </Badge>
                  </div>
                </div>
                
                <div>
                  <label className="text-sm font-medium text-muted-foreground">Created</label>
                  <p className="text-sm">
                    {formatDistanceToNow(new Date(website.created_at), { addSuffix: true })}
                  </p>
                </div>
              </div>

              {website.is_archived && (
                <div>
                  <label className="text-sm font-medium text-muted-foreground">Archived</label>
                  <p className="text-sm">
                    {website.archived_at ? formatDistanceToNow(new Date(website.archived_at), { addSuffix: true }) : 'Unknown'}
                  </p>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </AppLayout>
  )
}

