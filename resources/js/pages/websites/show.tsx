import { Head, usePage, Link, useForm } from '@inertiajs/react'
import AppLayout from '@/layouts/app-layout'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { formatDistanceToNow } from 'date-fns'
import { Globe, ArrowLeft, Plus, Trash2, RotateCcw, Copy, Check, Key, Pencil, X, Archive, ArchiveRestore } from 'lucide-react'
import { Icon } from '@/components/icon'
import { cn } from '@/lib/utils'
import WooCommerceIcon from '@/components/icons/woocommerce-icon'
import ShopifyIcon from '@/components/icons/shopify-icon'
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

interface Pixel {
  id: number
  platform: string
  name: string
  is_active: boolean
  pixel_id?: string | null
  access_token?: string | null
  conversion_id?: string | null
  conversion_labels?: any
  tag_id?: string | null
  ad_account_id?: string | null
  snapchat_pixel_id?: string | null
  event_ids?: any
  public_api_key?: string | null
  private_api_key?: string | null
  created_at: string
  updated_at: string
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
  pixels?: Pixel[]
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
  const [isEditingName, setIsEditingName] = useState(false)
  const [isEditingUrl, setIsEditingUrl] = useState(false)
  const [editedName, setEditedName] = useState('')
  const [editedUrl, setEditedUrl] = useState('')
  const [showPixelDialog, setShowPixelDialog] = useState(false)
  const [selectedPlatform, setSelectedPlatform] = useState<string | null>(null)
  const [selectedPixel, setSelectedPixel] = useState<Pixel | null>(null)

  const { data, setData, post, processing, errors, reset } = useForm({
    name: '',
  })
  
  const { 
    data: editData, 
    setData: setEditData, 
    patch: patchEdit, 
    processing: editProcessing, 
    reset: resetEdit 
  } = useForm({
    name: website.name,
    url: website.url,
  })
  
  const { 
    data: pixelData, 
    setData: setPixelData, 
    post: postPixel, 
    patch: patchPixel,
    processing: pixelProcessing, 
    reset: resetPixel 
  } = useForm({
    platform: '',
    name: '',
    pixel_id: '',
    access_token: '',
    conversion_id: '',
    conversion_labels: [],
    tag_id: '',
    ad_account_id: '',
    snapchat_pixel_id: '',
    event_ids: [],
    public_api_key: '',
    private_api_key: '',
    is_active: true,
  })

  // Update state when props change (e.g., after refresh)
  useEffect(() => {
    setWebsite(initialWebsite)
    setEditedName(initialWebsite.name)
    setEditedUrl(initialWebsite.url)
  }, [initialWebsite])

  // Update pixel form when platform changes
  useEffect(() => {
    if (selectedPlatform) {
      setPixelData('platform', selectedPlatform)
    }
  }, [selectedPlatform, setPixelData])

  // Populate form when editing a pixel
  useEffect(() => {
    if (selectedPixel) {
      setPixelData({
        platform: selectedPixel.platform,
        name: selectedPixel.name,
        pixel_id: selectedPixel.pixel_id || '',
        access_token: selectedPixel.access_token || '',
        conversion_id: selectedPixel.conversion_id || '',
        conversion_labels: selectedPixel.conversion_labels || [],
        tag_id: selectedPixel.tag_id || '',
        ad_account_id: selectedPixel.ad_account_id || '',
        snapchat_pixel_id: selectedPixel.snapchat_pixel_id || '',
        event_ids: selectedPixel.event_ids || [],
        public_api_key: selectedPixel.public_api_key || '',
        private_api_key: selectedPixel.private_api_key || '',
        is_active: selectedPixel.is_active,
      })
    }
  }, [selectedPixel, setPixelData])

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

  const handleStartEditName = () => {
    setIsEditingName(true)
    setEditedName(website.name)
  }

  const handleSaveName = () => {
    patchEdit(`/websites/${website.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        setIsEditingName(false)
      },
      onError: () => {
        setIsEditingName(false)
      }
    })
  }

  const handleCancelEditName = () => {
    setIsEditingName(false)
    setEditedName(website.name)
  }

  const handleStartEditUrl = () => {
    setIsEditingUrl(true)
    setEditedUrl(website.url)
  }

  const handleSaveUrl = () => {
    patchEdit(`/websites/${website.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        setIsEditingUrl(false)
      },
      onError: () => {
        setIsEditingUrl(false)
      }
    })
  }

  const handleCancelEditUrl = () => {
    setIsEditingUrl(false)
    setEditedUrl(website.url)
  }

  const handleOpenPixelDialog = (platform: string) => {
    setSelectedPlatform(platform)
    setSelectedPixel(null)
    setShowPixelDialog(true)
    // Reset form data
    resetPixel()
  }

  const handleTogglePixel = (platform: string) => {
    const platformPixels = website.pixels?.filter(p => p.platform === platform) || []
    
    if (platformPixels.length === 0) {
      // No pixels exist, open dialog to create one
      handleOpenPixelDialog(platform)
    } else {
      // Toggle all pixels active state
      const newActiveState = platformPixels.some(p => p.is_active) ? false : true
      platformPixels.forEach(pixel => {
        router.patch(`/websites/${website.id}/pixels/${pixel.id}`, { is_active: newActiveState }, {
          preserveScroll: true,
        })
      })
    }
  }

  const renderPlatformCard = (platform: string, label: string, icon: React.ReactNode) => {
    const platformPixels = website.pixels?.filter(p => p.platform === platform) || []
    
    return (
      <div className="rounded-lg border">
        <div className="flex items-center justify-between p-4 border-b">
          <div className="flex items-center gap-3">
            {icon}
            <div>
              <div className="font-medium">{label}</div>
              <div className="text-sm text-muted-foreground">
                {platformPixels.length || 0} connected
              </div>
            </div>
          </div>
          <Switch checked={platformPixels.some(p => p.is_active)} onCheckedChange={() => handleTogglePixel(platform)} />
        </div>
        {platformPixels.some(p => p.is_active) && platformPixels.map((pixel) => (
          <div key={pixel.id} className="px-4 py-3 flex items-center justify-between border-b last:border-b-0">
            <div className="flex items-center gap-2">
              <span className="font-mono text-sm">{pixel.name}</span>
              <span className={cn("h-2 w-2 rounded-full", pixel.is_active ? "bg-green-500" : "bg-gray-300")} />
            </div>
            <div className="flex items-center gap-2">
              <Button variant="ghost" size="sm" onClick={() => {
                setSelectedPixel(pixel)
                setSelectedPlatform(pixel.platform)
                setShowPixelDialog(true)
              }}>
                <Pencil className="h-4 w-4" />
              </Button>
              <Button variant="ghost" size="sm" onClick={() => {
                if (confirm('Are you sure you want to delete this pixel?')) {
                  router.delete(`/websites/${website.id}/pixels/${pixel.id}`, {
                    preserveScroll: true,
                  })
                }
              }}>
                <X className="h-4 w-4 text-red-500" />
              </Button>
            </div>
          </div>
        ))}
        {platformPixels.some(p => p.is_active) && (
          <div className="px-4 py-3">
            <Button variant="outline" size="sm" className="w-full border-dashed" onClick={() => handleOpenPixelDialog(platform)}>
              <Icon iconNode={Plus} className="mr-2 h-4 w-4" />
              Add pixel
            </Button>
          </div>
        )}
      </div>
    )
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

  const isPixelFormValid = () => {
    if (!pixelData.name) return false
    
    switch (selectedPlatform) {
      case 'meta':
      case 'tiktok':
      case 'reddit':
        return !!pixelData.pixel_id && !!pixelData.access_token
      case 'google':
        return !!pixelData.conversion_id
      case 'pinterest':
        return !!pixelData.tag_id && !!pixelData.ad_account_id && !!pixelData.access_token
      case 'snapchat':
        return !!pixelData.snapchat_pixel_id && !!pixelData.access_token
      case 'x':
        return !!pixelData.pixel_id
      case 'klaviyo':
        return !!pixelData.public_api_key && !!pixelData.private_api_key
      default:
        return false
    }
  }

  return (
    <AppLayout>
      <Head title={`${website.name} - Websites`} />
      
      <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div className="space-y-6">
          {/* Header */}
          <div className="space-y-4">
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
                    {isEditingName ? (
                      <div className="flex items-center gap-2">
                        <Input
                          value={editedName}
                          onChange={(e) => {
                            setEditedName(e.target.value)
                            setEditData('name', e.target.value)
                          }}
                          className="text-3xl font-bold h-auto py-1"
                          autoFocus
                          onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                              handleSaveName()
                            } else if (e.key === 'Escape') {
                              handleCancelEditName()
                            }
                          }}
                        />
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={handleSaveName}
                          disabled={editProcessing}
                        >
                          <Check className="h-4 w-4 text-green-600" />
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={handleCancelEditName}
                          disabled={editProcessing}
                        >
                          <X className="h-4 w-4 text-red-600" />
                        </Button>
                      </div>
                    ) : (
                      <h1 
                        className={cn(
                          "text-3xl font-bold tracking-tight cursor-pointer hover:text-primary transition-colors inline-flex items-center gap-2",
                          website.is_archived && "line-through text-muted-foreground"
                        )}
                        onClick={!website.is_archived ? handleStartEditName : undefined}
                      >
                        {website.name}
                        {!website.is_archived && (
                          <Pencil className="h-4 w-4 opacity-50" />
                        )}
                      </h1>
                    )}
                    {isEditingUrl ? (
                      <div className="flex items-center gap-2 mt-1">
                        <Input
                          value={editedUrl}
                          onChange={(e) => {
                            setEditedUrl(e.target.value)
                            setEditData('url', e.target.value)
                          }}
                          className="text-sm h-auto py-1"
                          autoFocus
                          onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                              handleSaveUrl()
                            } else if (e.key === 'Escape') {
                              handleCancelEditUrl()
                            }
                          }}
                        />
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={handleSaveUrl}
                          disabled={editProcessing}
                        >
                          <Check className="h-4 w-4 text-green-600" />
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={handleCancelEditUrl}
                          disabled={editProcessing}
                        >
                          <X className="h-4 w-4 text-red-600" />
                        </Button>
                      </div>
                    ) : (
                      <div className="flex items-center gap-2">
                        <p 
                          className={cn(
                            "text-muted-foreground cursor-pointer hover:text-primary transition-colors inline-flex items-center gap-1",
                            website.is_archived && "line-through"
                          )}
                          onClick={!website.is_archived ? handleStartEditUrl : undefined}
                        >
                          {website.type === 'woocommerce' ? 'WooCommerce' : 'Shopify'} Website • {website.url}
                          {!website.is_archived && (
                            <Pencil className="h-3 w-3 opacity-50" />
                          )}
                        </p>
                      </div>
                    )}
                  </div>
                </div>
              </div>
              
              <div className="flex items-center gap-3">
                {!website.is_archived && (
                  <div className="flex items-center gap-2">
                    <Label htmlFor="status-toggle" className="text-sm font-medium">
                      Active
                    </Label>
                    <Switch
                      id="status-toggle"
                      checked={website.status === 'active'}
                      onCheckedChange={handleStatusToggle}
                    />
                  </div>
                )}
                {website.is_archived ? (
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={handleUnarchive}
                  >
                    <Icon iconNode={ArchiveRestore} className="mr-2 h-4 w-4" />
                    Unarchive
                  </Button>
                ) : (
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={handleArchive}
                  >
                    <Icon iconNode={Archive} className="mr-2 h-4 w-4" />
                    Archive
                  </Button>
                )}
              </div>
            </div>
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

          {/* Connection Error */}
          {website.connection_error && (
            <Alert variant="destructive">
              <AlertTitle>Connection Error</AlertTitle>
              <AlertDescription>{website.connection_error}</AlertDescription>
            </Alert>
          )}

          {/* Main Content - Two Column Layout */}
          {!website.is_archived && (
            <div className="grid gap-6 md:grid-cols-2">
              {/* API Tokens Column */}
              <Card>
                <CardHeader>
                  <div>
                    <CardTitle>API Tokens</CardTitle>
                    <CardDescription>
                      Manage ingestion tokens for this website
                    </CardDescription>
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
                <div className="px-6 pb-6 pt-4 border-t">
                  <Button
                    variant="default"
                    size="lg"
                    className="w-full"
                    onClick={() => setShowCreateTokenDialog(true)}
                  >
                    <Icon iconNode={Plus} className="mr-2 h-5 w-5" />
                    Create New Token
                  </Button>
                </div>
              </Card>

              {/* Platform Pixels Column */}
              <Card>
                <CardHeader>
                  <div>
                    <CardTitle>Platform Pixels</CardTitle>
                    <CardDescription>
                      Connect tracking pixels from various platforms
                    </CardDescription>
                  </div>
                </CardHeader>
                <CardContent>
                  <div className="space-y-3">
                    {renderPlatformCard('meta', 'Meta Ads', <div className="h-10 w-10 rounded bg-blue-500 flex items-center justify-center text-white font-bold">M</div>)}
                    {renderPlatformCard('google', 'Google Ads', <div className="h-10 w-10 rounded bg-gradient-to-br from-blue-500 via-red-500 to-yellow-500 flex items-center justify-center text-white font-bold">G</div>)}
                    {renderPlatformCard('tiktok', 'TikTok Ads', <div className="h-10 w-10 rounded bg-black flex items-center justify-center text-white">𝄞</div>)}
                    {renderPlatformCard('pinterest', 'Pinterest Ads', <div className="h-10 w-10 rounded bg-red-500 flex items-center justify-center text-white font-bold">P</div>)}
                    {renderPlatformCard('snapchat', 'Snapchat Ads', <div className="h-10 w-10 rounded bg-yellow-500 flex items-center justify-center"><svg className="h-6 w-6 text-black" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-2-10c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm4 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm2 6c0-2.21-1.79-4-4-4s-4 1.79-4 4z"/></svg></div>)}
                    {renderPlatformCard('x', 'X Ads', <div className="h-10 w-10 rounded bg-black flex items-center justify-center text-white font-bold">X</div>)}
                    {renderPlatformCard('klaviyo', 'Klaviyo', <div className="h-10 w-10 rounded bg-purple-600 flex items-center justify-center text-white font-bold">K</div>)}
                    {renderPlatformCard('reddit', 'Reddit Ads', <div className="h-10 w-10 rounded bg-orange-500 flex items-center justify-center text-white">🔴</div>)}
                  </div>
                </CardContent>
              </Card>
            </div>
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

      {/* Pixel Dialog */}
      <Dialog open={showPixelDialog} onOpenChange={setShowPixelDialog}>
        <DialogContent className="max-w-md min-h-[700px] flex flex-col">
          <DialogHeader>
            <DialogTitle>{selectedPixel ? 'Edit Pixel' : 'Add Pixel'}</DialogTitle>
            <DialogDescription>
              {selectedPixel ? 'Update your tracking pixel configuration' : 'Connect your tracking pixel to start tracking conversions'}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 flex-1">
            {/* Platform Selection */}
            {!selectedPixel && (
              <div>
                <Label className="text-sm font-semibold mb-2 block">Platform</Label>
                <div className="grid grid-cols-4 gap-2">
                <button
                  type="button"
                  onClick={() => setSelectedPlatform('meta')}
                  className={cn(
                    "p-3 rounded-lg border-2 transition-all cursor-pointer",
                    selectedPlatform === 'meta' ? "border-blue-500 bg-blue-50 dark:bg-blue-950/20" : "border-gray-200 hover:border-gray-300"
                  )}
                >
                  <div className="h-10 w-10 rounded bg-blue-500 flex items-center justify-center text-white font-bold mx-auto">M</div>
                </button>
                <button
                  type="button"
                  onClick={() => setSelectedPlatform('google')}
                  className={cn(
                    "p-3 rounded-lg border-2 transition-all cursor-pointer",
                    selectedPlatform === 'google' ? "border-blue-500 bg-blue-50 dark:bg-blue-950/20" : "border-gray-200 hover:border-gray-300"
                  )}
                >
                  <div className="h-10 w-10 rounded bg-gradient-to-br from-blue-500 via-red-500 to-yellow-500 flex items-center justify-center text-white font-bold mx-auto">G</div>
                </button>
                <button
                  type="button"
                  onClick={() => setSelectedPlatform('tiktok')}
                  className={cn(
                    "p-3 rounded-lg border-2 transition-all cursor-pointer",
                    selectedPlatform === 'tiktok' ? "border-blue-500 bg-blue-50 dark:bg-blue-950/20" : "border-gray-200 hover:border-gray-300"
                  )}
                >
                  <div className="h-10 w-10 rounded bg-black flex items-center justify-center text-white mx-auto">𝄞</div>
                </button>
                <button
                  type="button"
                  onClick={() => setSelectedPlatform('pinterest')}
                  className={cn(
                    "p-3 rounded-lg border-2 transition-all cursor-pointer",
                    selectedPlatform === 'pinterest' ? "border-blue-500 bg-blue-50 dark:bg-blue-950/20" : "border-gray-200 hover:border-gray-300"
                  )}
                >
                  <div className="h-10 w-10 rounded bg-red-500 flex items-center justify-center text-white font-bold mx-auto">P</div>
                </button>
                <button
                  type="button"
                  onClick={() => setSelectedPlatform('snapchat')}
                  className={cn(
                    "p-3 rounded-lg border-2 transition-all cursor-pointer",
                    selectedPlatform === 'snapchat' ? "border-blue-500 bg-blue-50 dark:bg-blue-950/20" : "border-gray-200 hover:border-gray-300"
                  )}
                >
                  <div className="h-10 w-10 rounded bg-yellow-500 flex items-center justify-center mx-auto">
                    <svg className="h-6 w-6 text-black" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-2-10c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm4 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm2 6c0-2.21-1.79-4-4-4s-4 1.79-4 4z"/>
                    </svg>
                  </div>
                </button>
                <button
                  type="button"
                  onClick={() => setSelectedPlatform('x')}
                  className={cn(
                    "p-3 rounded-lg border-2 transition-all cursor-pointer",
                    selectedPlatform === 'x' ? "border-blue-500 bg-blue-50 dark:bg-blue-950/20" : "border-gray-200 hover:border-gray-300"
                  )}
                >
                  <div className="h-10 w-10 rounded bg-black flex items-center justify-center text-white font-bold mx-auto">X</div>
                </button>
                <button
                  type="button"
                  onClick={() => setSelectedPlatform('klaviyo')}
                  className={cn(
                    "p-3 rounded-lg border-2 transition-all cursor-pointer",
                    selectedPlatform === 'klaviyo' ? "border-blue-500 bg-blue-50 dark:bg-blue-950/20" : "border-gray-200 hover:border-gray-300"
                  )}
                >
                  <div className="h-10 w-10 rounded bg-purple-600 flex items-center justify-center text-white font-bold mx-auto">K</div>
                </button>
                <button
                  type="button"
                  onClick={() => setSelectedPlatform('reddit')}
                  className={cn(
                    "p-3 rounded-lg border-2 transition-all cursor-pointer",
                    selectedPlatform === 'reddit' ? "border-blue-500 bg-blue-50 dark:bg-blue-950/20" : "border-gray-200 hover:border-gray-300"
                  )}
                >
                  <div className="h-10 w-10 rounded bg-orange-500 flex items-center justify-center text-white mx-auto">🔴</div>
                </button>
              </div>
              </div>
            )}

            <div>
              <Label htmlFor="pixel-name">Name (optional)</Label>
              <Input
                id="pixel-name"
                value={pixelData.name}
                onChange={(e) => setPixelData('name', e.target.value)}
                placeholder="e.g. My ad account 1"
                className="mt-1"
              />
              <p className="text-xs text-muted-foreground mt-1">Name as displayed in our app's user interfaces.</p>
            </div>
            
            {selectedPlatform === 'meta' && (
              <>
                <div>
                  <Label htmlFor="pixel-id">Pixel ID</Label>
                  <Input
                    id="pixel-id"
                    value={pixelData.pixel_id}
                    onChange={(e) => setPixelData('pixel_id', e.target.value)}
                    placeholder="e.g. 1234567891234567"
                    className="mt-1"
                  />
                  <p className="text-xs text-muted-foreground mt-1">Find your Pixel ID with <a href="#" className="text-blue-600 hover:underline">these steps</a>.</p>
                </div>
                <div>
                  <Label htmlFor="access-token">Access Token</Label>
                  <Input
                    id="access-token"
                    type="password"
                    value={pixelData.access_token}
                    onChange={(e) => setPixelData('access_token', e.target.value)}
                    placeholder="e.g. EAAHZBAX38..."
                    className="mt-1"
                  />
                  <p className="text-xs text-muted-foreground mt-1">Find your Access token with <a href="#" className="text-blue-600 hover:underline">these steps</a>.</p>
                </div>
              </>
            )}
            
            {selectedPlatform === 'google' && (
              <div>
                <Label htmlFor="conversion-id">Conversion ID</Label>
                <Input
                  id="conversion-id"
                  value={pixelData.conversion_id}
                  onChange={(e) => setPixelData('conversion_id', e.target.value)}
                  placeholder="Enter Google Conversion ID"
                  className="mt-1"
                />
              </div>
            )}
            
            {selectedPlatform === 'tiktok' && (
              <>
                <div>
                  <Label htmlFor="pixel-id-tiktok">Pixel ID</Label>
                  <Input
                    id="pixel-id-tiktok"
                    value={pixelData.pixel_id}
                    onChange={(e) => setPixelData('pixel_id', e.target.value)}
                    placeholder="Enter TikTok Pixel ID"
                    className="mt-1"
                  />
                </div>
                <div>
                  <Label htmlFor="access-token-tiktok">Access Token</Label>
                  <Input
                    id="access-token-tiktok"
                    type="password"
                    value={pixelData.access_token}
                    onChange={(e) => setPixelData('access_token', e.target.value)}
                    placeholder="Enter Access Token"
                    className="mt-1"
                  />
                </div>
              </>
            )}
            
            {selectedPlatform === 'pinterest' && (
              <>
                <div>
                  <Label htmlFor="tag-id">Tag ID</Label>
                  <Input
                    id="tag-id"
                    value={pixelData.tag_id}
                    onChange={(e) => setPixelData('tag_id', e.target.value)}
                    placeholder="Enter Pinterest Tag ID"
                    className="mt-1"
                  />
                </div>
                <div>
                  <Label htmlFor="ad-account-id">Ad Account ID</Label>
                  <Input
                    id="ad-account-id"
                    value={pixelData.ad_account_id}
                    onChange={(e) => setPixelData('ad_account_id', e.target.value)}
                    placeholder="Enter Ad Account ID"
                    className="mt-1"
                  />
                </div>
                <div>
                  <Label htmlFor="access-token-pinterest">Access Token</Label>
                  <Input
                    id="access-token-pinterest"
                    type="password"
                    value={pixelData.access_token}
                    onChange={(e) => setPixelData('access_token', e.target.value)}
                    placeholder="Enter Access Token"
                    className="mt-1"
                  />
                </div>
              </>
            )}
            
            {selectedPlatform === 'snapchat' && (
              <>
                <div>
                  <Label htmlFor="snapchat-pixel-id">Pixel ID</Label>
                  <Input
                    id="snapchat-pixel-id"
                    value={pixelData.snapchat_pixel_id}
                    onChange={(e) => setPixelData('snapchat_pixel_id', e.target.value)}
                    placeholder="Enter Snapchat Pixel ID"
                    className="mt-1"
                  />
                </div>
                <div>
                  <Label htmlFor="access-token-snapchat">Access Token</Label>
                  <Input
                    id="access-token-snapchat"
                    type="password"
                    value={pixelData.access_token}
                    onChange={(e) => setPixelData('access_token', e.target.value)}
                    placeholder="Enter Access Token"
                    className="mt-1"
                  />
                </div>
              </>
            )}
            
            {selectedPlatform === 'x' && (
              <div>
                <Label htmlFor="pixel-id-x">Pixel ID</Label>
                <Input
                  id="pixel-id-x"
                  value={pixelData.pixel_id}
                  onChange={(e) => setPixelData('pixel_id', e.target.value)}
                  placeholder="Enter X Pixel ID"
                  className="mt-1"
                />
              </div>
            )}
            
            {selectedPlatform === 'klaviyo' && (
              <>
                <div>
                  <Label htmlFor="public-api-key">Public API Key</Label>
                  <Input
                    id="public-api-key"
                    type="password"
                    value={pixelData.public_api_key}
                    onChange={(e) => setPixelData('public_api_key', e.target.value)}
                    placeholder="Enter Public API Key"
                    className="mt-1"
                  />
                </div>
                <div>
                  <Label htmlFor="private-api-key">Private API Key</Label>
                  <Input
                    id="private-api-key"
                    type="password"
                    value={pixelData.private_api_key}
                    onChange={(e) => setPixelData('private_api_key', e.target.value)}
                    placeholder="Enter Private API Key"
                    className="mt-1"
                  />
                </div>
              </>
            )}
            
            {selectedPlatform === 'reddit' && (
              <>
                <div>
                  <Label htmlFor="pixel-id-reddit">Pixel ID</Label>
                  <Input
                    id="pixel-id-reddit"
                    value={pixelData.pixel_id}
                    onChange={(e) => setPixelData('pixel_id', e.target.value)}
                    placeholder="Enter Reddit Pixel ID"
                    className="mt-1"
                  />
                </div>
                <div>
                  <Label htmlFor="access-token-reddit">Access Token</Label>
                  <Input
                    id="access-token-reddit"
                    type="password"
                    value={pixelData.access_token}
                    onChange={(e) => setPixelData('access_token', e.target.value)}
                    placeholder="Enter Access Token"
                    className="mt-1"
                  />
                </div>
              </>
            )}
          </div>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => {
                setShowPixelDialog(false)
                resetPixel()
                setSelectedPlatform(null)
                setSelectedPixel(null)
              }}
            >
              Cancel
            </Button>
            <Button
              onClick={() => {
                if (!selectedPlatform) return
                
                if (selectedPixel) {
                  // Update existing pixel
                  patchPixel(`/websites/${website.id}/pixels/${selectedPixel.id}`, {
                    preserveScroll: true,
                    onSuccess: () => {
                      setShowPixelDialog(false)
                      resetPixel()
                      setSelectedPlatform(null)
                      setSelectedPixel(null)
                    }
                  })
                } else {
                  // Create new pixel
                  postPixel(`/websites/${website.id}/pixels`, {
                    preserveScroll: true,
                    onSuccess: () => {
                      setShowPixelDialog(false)
                      resetPixel()
                      setSelectedPlatform(null)
                    }
                  })
                }
              }}
              disabled={pixelProcessing || !selectedPlatform || !isPixelFormValid()}
            >
              {pixelProcessing ? (selectedPixel ? 'Updating...' : 'Adding...') : (selectedPixel ? 'Update pixel' : 'Add pixel')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AppLayout>
  )
}
