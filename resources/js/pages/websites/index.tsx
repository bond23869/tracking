import { Head, usePage, router } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/layouts/app-layout'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { formatDistanceToNow } from 'date-fns'
import { Globe, Plus, Check, Archive, ArchiveRestore, MoreVertical } from 'lucide-react'
import { Icon } from '@/components/icon'
import { cn } from '@/lib/utils'
import WooCommerceIcon from '@/components/icons/woocommerce-icon'
import ShopifyIcon from '@/components/icons/shopify-icon'
import InputError from '@/components/input-error'
import { Checkbox } from '@/components/ui/checkbox'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

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

interface WebsitePageProps {
  websites: Website[]
  currentAccount?: {
    id: number
    name: string
    slug: string
  } | null
  showArchived?: boolean
  errors?: Record<string, string>
  success?: string
}

export default function WebsitesIndex() {
  const { websites, currentAccount, showArchived = false } = usePage<WebsitePageProps>().props
  const [showCreateDialog, setShowCreateDialog] = useState(false)
  const [form, setForm] = useState({ 
    type: 'woocommerce' as 'woocommerce' | 'shopify', 
    url: '' 
  })
  const [processing, setProcessing] = useState(false)
  const [urlError, setUrlError] = useState<string | null>(null)

  const handleToggleShowArchived = (checked: boolean) => {
    router.get('/websites', { show_archived: checked ? '1' : '0' }, {
      preserveState: true,
      preserveScroll: true,
    })
  }

  const handleArchive = (websiteId: number) => {
    router.post(`/websites/${websiteId}/archive`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        // If showing archived websites, refresh to update the list
        if (showArchived) {
          router.reload({ only: ['websites'] })
        }
      },
    })
  }

  const handleUnarchive = (websiteId: number) => {
    router.post(`/websites/${websiteId}/unarchive`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        // Refresh to update the list
        router.reload({ only: ['websites'] })
      },
    })
  }

  const validateUrl = (url: string): string | null => {
    if (!url.trim()) {
      return 'URL is required'
    }

    // Try to parse the URL
    try {
      let urlToValidate = url.trim()
      
      // If URL doesn't start with http:// or https://, try to add https://
      if (!urlToValidate.match(/^https?:\/\//i)) {
        urlToValidate = `https://${urlToValidate}`
      }
      
      const urlObj = new URL(urlToValidate)
      
      // Check if it has a valid protocol
      if (!['http:', 'https:'].includes(urlObj.protocol)) {
        return 'URL must use http:// or https://'
      }
      
      // Check if it has a hostname
      if (!urlObj.hostname) {
        return 'Please enter a valid URL'
      }
      
      return null
    } catch {
      return 'Please enter a valid URL (e.g., https://example.com)'
    }
  }

  const handleUrlChange = (url: string) => {
    setForm({ ...form, url })
    // Clear error if URL becomes valid
    if (urlError) {
      const error = validateUrl(url)
      setUrlError(error)
    }
  }

  const handleUrlBlur = () => {
    const error = validateUrl(form.url)
    setUrlError(error)
  }

  const handleCreateWebsite = (e: React.FormEvent) => {
    e.preventDefault()
    
    // Validate URL before submission
    const error = validateUrl(form.url)
    if (error) {
      setUrlError(error)
      return
    }
    
    setProcessing(true)
    
    router.post('/websites', form, {
      onSuccess: () => {
        setShowCreateDialog(false)
        setForm({ type: 'woocommerce', url: '' })
        setUrlError(null)
      },
      onFinish: () => setProcessing(false)
    })
  }

  const handleDialogChange = (open: boolean) => {
    setShowCreateDialog(open)
    if (!open) {
      // Reset form and errors when dialog closes
      setForm({ type: 'woocommerce', url: '' })
      setUrlError(null)
    }
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
              <Dialog open={showCreateDialog} onOpenChange={handleDialogChange}>
                <DialogTrigger asChild>
                  <Button className="mt-2">
                    <Icon iconNode={Plus} className="mr-2 h-4 w-4" />
                    Add a new website
                  </Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader>
                    <DialogTitle>Create Website</DialogTitle>
                    <DialogDescription>
                      Add a new website to your account
                    </DialogDescription>
                  </DialogHeader>
                  <form onSubmit={handleCreateWebsite} className="space-y-4">
                    <div>
                      <Label>Website Type</Label>
                      <div className="grid grid-cols-2 gap-3 mt-2">
                        <button
                          type="button"
                          onClick={() => setForm({ ...form, type: 'woocommerce' })}
                          className={cn(
                            "relative flex flex-col items-center justify-center gap-2 p-4 border rounded-lg transition-all hover:border-primary cursor-pointer",
                            form.type === 'woocommerce' ? "border-primary bg-primary/5" : "border-input"
                          )}
                        >
                          {form.type === 'woocommerce' && (
                            <div className="absolute top-2 right-2">
                              <Icon iconNode={Check} className="h-5 w-5 text-primary" />
                            </div>
                          )}
                          <WooCommerceIcon className="h-12 w-12" />
                          <div className="text-lg font-semibold">WooCommerce</div>
                        </button>
                        <button
                          type="button"
                          onClick={() => setForm({ ...form, type: 'shopify' })}
                          className={cn(
                            "relative flex flex-col items-center justify-center gap-2 p-4 border rounded-lg transition-all hover:border-primary cursor-pointer",
                            form.type === 'shopify' ? "border-primary bg-primary/5" : "border-input"
                          )}
                        >
                          {form.type === 'shopify' && (
                            <div className="absolute top-2 right-2">
                              <Icon iconNode={Check} className="h-5 w-5 text-primary" />
                            </div>
                          )}
                          <ShopifyIcon className="h-12 w-12" />
                          <div className="text-lg font-semibold">Shopify</div>
                        </button>
                      </div>
                    </div>
                    <div>
                      <Label htmlFor="url">Website URL</Label>
                      <Input
                        id="url"
                        type="url"
                        value={form.url}
                        onChange={(e) => handleUrlChange(e.target.value)}
                        onBlur={handleUrlBlur}
                        placeholder="https://example.com"
                        className={cn(urlError && "border-red-500 focus-visible:ring-red-500")}
                        required
                        autoFocus
                      />
                      <InputError message={urlError || undefined} className="mt-1" />
                    </div>
                    <div className="flex justify-end space-x-2">
                      <Button type="button" variant="outline" onClick={() => handleDialogChange(false)}>
                        Cancel
                      </Button>
                      <Button type="submit" disabled={processing || !!urlError}>
                        {processing ? 'Creating...' : 'Create Website'}
                      </Button>
                    </div>
                  </form>
                </DialogContent>
              </Dialog>
            </div>
          </div>
        ) : (
          <div className="space-y-6">
            <div className="flex items-center justify-between">
              <div>
                <h1 className="text-3xl font-bold tracking-tight">Websites</h1>
                <p className="text-muted-foreground">
                  Manage websites for {currentAccount.name}
                </p>
              </div>
              
              <div className="flex items-center gap-4">
                <div className="flex items-center space-x-2">
                  <Checkbox
                    id="show-archived"
                    checked={showArchived}
                    onCheckedChange={(checked) => handleToggleShowArchived(checked === true)}
                  />
                  <Label
                    htmlFor="show-archived"
                    className="text-sm font-normal cursor-pointer"
                  >
                    Show archived
                  </Label>
                </div>
                
                <Dialog open={showCreateDialog} onOpenChange={handleDialogChange}>
                <DialogTrigger asChild>
                  <Button>
                    <Icon iconNode={Plus} className="mr-2 h-4 w-4" />
                    Create Website
                  </Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader>
                    <DialogTitle>Create Website</DialogTitle>
                    <DialogDescription>
                      Add a new website to your account
                    </DialogDescription>
                  </DialogHeader>
                  <form onSubmit={handleCreateWebsite} className="space-y-4">
                    <div>
                      <Label>Website Type</Label>
                      <div className="grid grid-cols-2 gap-3 mt-2">
                        <button
                          type="button"
                          onClick={() => setForm({ ...form, type: 'woocommerce' })}
                          className={cn(
                            "relative flex flex-col items-center justify-center gap-2 p-4 border rounded-lg transition-all hover:border-primary cursor-pointer",
                            form.type === 'woocommerce' ? "border-primary bg-primary/5" : "border-input"
                          )}
                        >
                          {form.type === 'woocommerce' && (
                            <div className="absolute top-2 right-2">
                              <Icon iconNode={Check} className="h-5 w-5 text-primary" />
                            </div>
                          )}
                          <WooCommerceIcon className="h-12 w-12" />
                          <div className="text-lg font-semibold">WooCommerce</div>
                        </button>
                        <button
                          type="button"
                          onClick={() => setForm({ ...form, type: 'shopify' })}
                          className={cn(
                            "relative flex flex-col items-center justify-center gap-2 p-4 border rounded-lg transition-all hover:border-primary cursor-pointer",
                            form.type === 'shopify' ? "border-primary bg-primary/5" : "border-input"
                          )}
                        >
                          {form.type === 'shopify' && (
                            <div className="absolute top-2 right-2">
                              <Icon iconNode={Check} className="h-5 w-5 text-primary" />
                            </div>
                          )}
                          <ShopifyIcon className="h-12 w-12" />
                          <div className="text-lg font-semibold">Shopify</div>
                        </button>
                      </div>
                    </div>
                    <div>
                      <Label htmlFor="create-url">Website URL</Label>
                      <Input
                        id="create-url"
                        type="url"
                        value={form.url}
                        onChange={(e) => handleUrlChange(e.target.value)}
                        onBlur={handleUrlBlur}
                        placeholder="https://example.com"
                        className={cn(urlError && "border-red-500 focus-visible:ring-red-500")}
                        required
                        autoFocus
                      />
                      <InputError message={urlError || undefined} className="mt-1" />
                    </div>
                    <div className="flex justify-end space-x-2">
                      <Button type="button" variant="outline" onClick={() => handleDialogChange(false)}>
                        Cancel
                      </Button>
                      <Button type="submit" disabled={processing || !!urlError}>
                        {processing ? 'Creating...' : 'Create Website'}
                      </Button>
                    </div>
                  </form>
                </DialogContent>
              </Dialog>
              </div>
            </div>

            {/* Websites List */}
            <div className="space-y-4">
              <div>
                <h2 className="text-2xl font-semibold tracking-tight">Websites ({websites.length})</h2>
                <p className="text-muted-foreground">
                  All websites under {currentAccount.name}
                </p>
              </div>
              
              {websites.length === 0 ? (
                <Card>
                  <CardContent className="flex flex-col items-center justify-center py-12">
                    <Icon iconNode={Globe} className="h-12 w-12 text-muted-foreground mb-4" />
                    <p className="text-muted-foreground">No websites found</p>
                  </CardContent>
                </Card>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  {websites.map((website) => (
                    <Card 
                      key={website.id}
                      className={cn(
                        "relative transition-all hover:shadow-md",
                        website.is_archived && "opacity-60"
                      )}
                    >
                      <CardHeader className="pb-3">
                        <div className="flex items-start justify-between">
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 mb-2">
                              {website.type === 'woocommerce' ? (
                                <WooCommerceIcon className="h-5 w-5 flex-shrink-0" />
                              ) : (
                                <ShopifyIcon className="h-5 w-5 flex-shrink-0" />
                              )}
                              <CardTitle className={cn(
                                "text-lg truncate",
                                website.is_archived && "line-through text-muted-foreground"
                              )}>
                                {website.name}
                              </CardTitle>
                            </div>
                            <div className={cn(
                              "text-sm text-muted-foreground truncate",
                              website.is_archived && "line-through"
                            )}>
                              <a 
                                href={website.url} 
                                target="_blank" 
                                rel="noopener noreferrer"
                                className="text-blue-600 hover:underline dark:text-blue-400"
                              >
                                {website.url}
                              </a>
                            </div>
                          </div>
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" size="icon" className="h-8 w-8 flex-shrink-0">
                                <Icon iconNode={MoreVertical} className="h-4 w-4" />
                                <span className="sr-only">Open menu</span>
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                              {website.is_archived ? (
                                <DropdownMenuItem onClick={() => handleUnarchive(website.id)}>
                                  <Icon iconNode={ArchiveRestore} className="mr-2 h-4 w-4" />
                                  Unarchive
                                </DropdownMenuItem>
                              ) : (
                                <DropdownMenuItem 
                                  onClick={() => handleArchive(website.id)}
                                  variant="destructive"
                                >
                                  <Icon iconNode={Archive} className="mr-2 h-4 w-4" />
                                  Archive
                                </DropdownMenuItem>
                              )}
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </div>
                      </CardHeader>
                      <CardContent className="pt-0">
                        <div className="space-y-3">
                          {website.connection_error && (
                            <div className="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/20 p-2 rounded border border-red-200 dark:border-red-800">
                              {website.connection_error}
                            </div>
                          )}
                          
                          <div className="flex flex-wrap items-center gap-2">
                            <Badge variant={getStatusBadgeVariant(website.status)}>
                              {website.status}
                            </Badge>
                            <Badge variant={getConnectionStatusBadgeVariant(website.connection_status)}>
                              {website.connection_status}
                            </Badge>
                            {website.is_archived && (
                              <Badge variant="secondary">
                                Archived
                              </Badge>
                            )}
                          </div>
                          
                          <div className="text-xs text-muted-foreground pt-2 border-t">
                            Created {formatDistanceToNow(new Date(website.created_at), { addSuffix: true })}
                          </div>
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  )
}
