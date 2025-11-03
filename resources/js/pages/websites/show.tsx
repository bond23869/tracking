import { Head, usePage, Link, useForm } from '@inertiajs/react'
import AppLayout from '@/layouts/app-layout'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { formatDistanceToNow } from 'date-fns'
import { Globe, ArrowLeft, Plus, Trash2, RotateCcw, Copy, Check, Key } from 'lucide-react'
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
import { Input } from '@/components/ui/input'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { useClipboard } from '@/hooks/use-clipboard'

interface IngestionToken {
  id: number
  name: string
  token_prefix: string
  last_used_at?: string | null
  expires_at?: string | null
  revoked_at?: string | null
  is_revoked: boolean
  created_at: string
}

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
  ingestion_tokens?: IngestionToken[]
  new_token?: {
    id: number
    name: string
    token: string
  }
}

interface WebsiteShowProps {
  website: Website
  [key: string]: unknown
}

export default function WebsiteShow() {
  const { website: initialWebsite } = usePage<WebsiteShowProps>().props
  const [website, setWebsite] = useState(initialWebsite)
  const [showCreateTokenDialog, setShowCreateTokenDialog] = useState(false)
  const [copiedText, copy] = useClipboard()

  // Update state when props change (e.g., after refresh)
  useEffect(() => {
    setWebsite(initialWebsite)
  }, [initialWebsite])

  const { data, setData, post, processing, errors, reset } = useForm({
    name: '',
  })

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

  const handleCreateToken = () => {
    post(`/websites/${website.id}/ingestion-tokens`, {
      preserveScroll: true,
      onSuccess: () => {
        reset()
        setShowCreateTokenDialog(false)
      }
    })
  }

  const handleDeleteToken = (tokenId: number) => {
    if (confirm('Are you sure you want to delete this token? This action cannot be undone.')) {
      router.delete(`/websites/${website.id}/ingestion-tokens/${tokenId}`, {
        preserveScroll: true,
      })
    }
  }

  const handleRevokeToken = (tokenId: number) => {
    router.post(`/websites/${website.id}/ingestion-tokens/${tokenId}/revoke`, {
      preserveScroll: true,
    })
  }

  const handleRestoreToken = (tokenId: number) => {
    router.post(`/websites/${website.id}/ingestion-tokens/${tokenId}/restore`, {
      preserveScroll: true,
    })
  }

  const handleCopyToken = (token: string) => {
    copy(token)
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

          {/* New Token Alert */}
          {website.new_token && (
            <Alert variant="default" className="border-yellow-500 bg-yellow-50 dark:bg-yellow-950/20">
              <Key className="h-4 w-4" />
              <AlertTitle>Save your token now!</AlertTitle>
              <AlertDescription>
                <div className="mt-2 space-y-2">
                  <p className="text-sm">
                    Your new token has been created. Copy it now - it won't be shown again.
                  </p>
                  <div className="flex items-center gap-2">
                    <Input
                      value={website.new_token.token}
                      readOnly
                      className="font-mono text-xs"
                    />
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => handleCopyToken(website.new_token!.token)}
                    >
                      {copiedText === website.new_token.token ? (
                        <>
                          <Check className="mr-2 h-4 w-4" />
                          Copied
                        </>
                      ) : (
                        <>
                          <Copy className="mr-2 h-4 w-4" />
                          Copy
                        </>
                      )}
                    </Button>
                  </div>
                </div>
              </AlertDescription>
            </Alert>
          )}

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

          {/* Ingestion Tokens */}
          {!website.is_archived && (
            <Card>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <div>
                    <CardTitle>API Tokens</CardTitle>
                    <CardDescription>
                      Manage ingestion tokens for this website
                    </CardDescription>
                  </div>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setShowCreateTokenDialog(true)}
                  >
                    <Icon iconNode={Plus} className="mr-2 h-4 w-4" />
                    Create Token
                  </Button>
                </div>
              </CardHeader>
              <CardContent>
                {website.ingestion_tokens && website.ingestion_tokens.length > 0 ? (
                  <div className="space-y-3">
                    {website.ingestion_tokens.map((token) => (
                      <div
                        key={token.id}
                        className={cn(
                          "flex items-center justify-between rounded-lg border p-4",
                          token.is_revoked && "opacity-60"
                        )}
                      >
                        <div className="flex-1">
                          <div className="flex items-center gap-2">
                            <span className="font-medium">{token.name}</span>
                            {token.is_revoked && (
                              <Badge variant="secondary">Revoked</Badge>
                            )}
                          </div>
                          <div className="mt-1 flex items-center gap-4 text-sm text-muted-foreground">
                            <span className="font-mono">
                              {token.token_prefix}...
                            </span>
                            {token.last_used_at && (
                              <span>
                                Last used {formatDistanceToNow(new Date(token.last_used_at), { addSuffix: true })}
                              </span>
                            )}
                            {!token.last_used_at && (
                              <span className="text-yellow-600 dark:text-yellow-400">
                                Never used
                              </span>
                            )}
                          </div>
                        </div>
                        <div className="flex items-center gap-2">
                          {token.is_revoked ? (
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => handleRestoreToken(token.id)}
                            >
                              <Icon iconNode={RotateCcw} className="mr-2 h-4 w-4" />
                              Restore
                            </Button>
                          ) : (
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => handleRevokeToken(token.id)}
                            >
                              <Icon iconNode={RotateCcw} className="mr-2 h-4 w-4" />
                              Revoke
                            </Button>
                          )}
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleDeleteToken(token.id)}
                          >
                            <Icon iconNode={Trash2} className="mr-2 h-4 w-4" />
                            Delete
                          </Button>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="text-center py-8 text-muted-foreground">
                    <Key className="mx-auto h-12 w-12 mb-4 opacity-50" />
                    <p>No tokens created yet</p>
                    <p className="text-sm mt-1">Create a token to start tracking events</p>
                  </div>
                )}
              </CardContent>
            </Card>
          )}
        </div>
      </div>

      {/* Create Token Dialog */}
      <Dialog open={showCreateTokenDialog} onOpenChange={setShowCreateTokenDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Create New Token</DialogTitle>
            <DialogDescription>
              Create a new ingestion token for this website. Give it a descriptive name.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div>
              <Label htmlFor="name">Token Name</Label>
              <Input
                id="name"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                placeholder="e.g., Production, Development"
                className="mt-1"
              />
              {errors.name && (
                <p className="mt-1 text-sm text-red-600">{errors.name}</p>
              )}
            </div>
          </div>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => {
                setShowCreateTokenDialog(false)
                reset()
              }}
            >
              Cancel
            </Button>
            <Button
              onClick={handleCreateToken}
              disabled={processing || !data.name}
            >
              {processing ? 'Creating...' : 'Create Token'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AppLayout>
  )
}
