import { Head, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/layouts/app-layout'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AlertError from '@/components/alert-error'
import { Icon } from '@/components/icon'
import { formatDistanceToNow } from 'date-fns'
import { Plus, MoreHorizontal, Edit, Archive } from 'lucide-react'
import { type Account } from '@/types'

interface AccountPageProps {
  accounts: Account[]
  errors?: Record<string, string>
  success?: string
}

export default function AccountsIndex() {
  const { accounts, errors, success } = usePage<AccountPageProps>().props
  const [showCreateDialog, setShowCreateDialog] = useState(false)
  const [showEditDialog, setShowEditDialog] = useState(false)
  const [showArchiveDialog, setShowArchiveDialog] = useState(false)
  const [selectedAccount, setSelectedAccount] = useState<Account | null>(null)
  const [form, setForm] = useState({ name: '' })
  const [processing, setProcessing] = useState(false)

  const activeAccounts = accounts.filter(account => !account.is_archived)
  const archivedAccounts = accounts.filter(account => account.is_archived)

  const handleCreateAccount = (e: React.FormEvent) => {
    e.preventDefault()
    setProcessing(true)
    
    router.post('/accounts', form, {
      onSuccess: () => {
        setShowCreateDialog(false)
        setForm({ name: '' })
      },
      onFinish: () => setProcessing(false)
    })
  }

  const handleUpdateAccount = (e: React.FormEvent) => {
    e.preventDefault()
    if (!selectedAccount) return
    
    setProcessing(true)
    
    router.patch(`/accounts/${selectedAccount.id}`, form, {
      onSuccess: () => {
        setShowEditDialog(false)
        setSelectedAccount(null)
        setForm({ name: '' })
      },
      onFinish: () => setProcessing(false)
    })
  }

  const handleArchiveAccount = () => {
    if (!selectedAccount) return
    
    setProcessing(true)
    
    router.post(`/accounts/${selectedAccount.id}/archive`, {}, {
      onSuccess: () => {
        setShowArchiveDialog(false)
        setSelectedAccount(null)
      },
      onFinish: () => setProcessing(false)
    })
  }

  const openEditDialog = (account: Account) => {
    setSelectedAccount(account)
    setForm({ name: account.name })
    setShowEditDialog(true)
  }

  const openArchiveDialog = (account: Account) => {
    setSelectedAccount(account)
    setShowArchiveDialog(true)
  }

  return (
    <AppLayout>
      <Head title="Accounts" />
      
      <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Accounts</h1>
            <p className="text-muted-foreground">
              Manage your organization's accounts
            </p>
          </div>
          
          <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
            <DialogTrigger asChild>
              <Button>
                <Icon iconNode={Plus} className="mr-2 h-4 w-4" />
                Create Account
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Create Account</DialogTitle>
                <DialogDescription>
                  Create a new account for your organization
                </DialogDescription>
              </DialogHeader>
              <form onSubmit={handleCreateAccount} className="space-y-4">
                <div>
                  <Label htmlFor="name">Account Name</Label>
                  <Input
                    id="name"
                    value={form.name}
                    onChange={(e) => setForm({ name: e.target.value })}
                    placeholder="Enter account name"
                    required
                    autoFocus
                  />
                </div>
                <div className="flex justify-end space-x-2">
                  <Button type="button" variant="outline" onClick={() => setShowCreateDialog(false)}>
                    Cancel
                  </Button>
                  <Button type="submit" disabled={processing}>
                    {processing ? 'Creating...' : 'Create Account'}
                  </Button>
                </div>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        {success && (
          <div className="rounded-md bg-green-50 p-4 dark:bg-green-900/20">
            <div className="text-sm text-green-700 dark:text-green-300">{success}</div>
          </div>
        )}

        {errors && Object.keys(errors).length > 0 && (
          <AlertError errors={Object.values(errors)} />
        )}

        {/* Active Accounts */}
        <Card>
          <CardHeader>
            <CardTitle>Active Accounts ({activeAccounts.length})</CardTitle>
            <CardDescription>
              Currently active accounts in your organization
            </CardDescription>
          </CardHeader>
          <CardContent>
            {activeAccounts.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-12 text-center">
                <div className="rounded-full bg-muted p-3 mb-4">
                  <Icon iconNode={Plus} className="h-6 w-6 text-muted-foreground" />
                </div>
                <h3 className="text-lg font-medium mb-2">No accounts yet</h3>
                <p className="text-muted-foreground mb-4">
                  Start by creating your first account.
                </p>
                <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
                  <DialogTrigger asChild>
                    <Button>
                      <Icon iconNode={Plus} className="mr-2 h-4 w-4" />
                      Create Your First Account
                    </Button>
                  </DialogTrigger>
                </Dialog>
              </div>
            ) : (
              <div className="rounded-md border">
                <table className="w-full">
                  <thead>
                    <tr className="border-b bg-muted/50">
                      <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                        Account Name
                      </th>
                      <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                        Created
                      </th>
                      <th className="h-12 px-4 text-right align-middle font-medium text-muted-foreground">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {activeAccounts.map((account) => (
                      <tr key={account.id} className="border-b transition-colors hover:bg-muted/50">
                        <td className="p-4 align-middle">
                          <div className="font-medium">{account.name}</div>
                          <div className="text-sm text-muted-foreground">{account.slug}</div>
                        </td>
                        <td className="p-4 align-middle">
                          <div className="text-sm">
                            {account.created_at && formatDistanceToNow(new Date(account.created_at), { addSuffix: true })}
                          </div>
                        </td>
                        <td className="p-4 align-middle text-right">
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" size="sm">
                                <Icon iconNode={MoreHorizontal} className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                              <DropdownMenuItem onClick={() => openEditDialog(account)}>
                                <Icon iconNode={Edit} className="mr-2 h-4 w-4" />
                                Edit Name
                              </DropdownMenuItem>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem 
                                onClick={() => openArchiveDialog(account)}
                                className="text-red-600 dark:text-red-400"
                              >
                                <Icon iconNode={Archive} className="mr-2 h-4 w-4" />
                                Archive Account
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Archived Accounts */}
        {archivedAccounts.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle>Archived Accounts ({archivedAccounts.length})</CardTitle>
              <CardDescription>
                Previously archived accounts
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="rounded-md border">
                <table className="w-full">
                  <thead>
                    <tr className="border-b bg-muted/50">
                      <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                        Account Name
                      </th>
                      <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                        Archived
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {archivedAccounts.map((account) => (
                      <tr key={account.id} className="border-b transition-colors hover:bg-muted/50">
                        <td className="p-4 align-middle">
                          <div className="font-medium">{account.name}</div>
                          <div className="text-sm text-muted-foreground">{account.slug}</div>
                        </td>
                        <td className="p-4 align-middle">
                          <div className="text-sm">
                            {account.archived_at && formatDistanceToNow(new Date(account.archived_at), { addSuffix: true })}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Edit Account Dialog */}
        <Dialog open={showEditDialog} onOpenChange={setShowEditDialog}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Edit Account</DialogTitle>
              <DialogDescription>
                Update the account name
              </DialogDescription>
            </DialogHeader>
            <form onSubmit={handleUpdateAccount} className="space-y-4">
              <div>
                <Label htmlFor="edit-name">Account Name</Label>
                <Input
                  id="edit-name"
                  value={form.name}
                  onChange={(e) => setForm({ name: e.target.value })}
                  required
                  autoFocus
                />
              </div>
              <div className="flex justify-end space-x-2">
                <Button type="button" variant="outline" onClick={() => setShowEditDialog(false)}>
                  Cancel
                </Button>
                <Button type="submit" disabled={processing}>
                  {processing ? 'Updating...' : 'Update Account'}
                </Button>
              </div>
            </form>
          </DialogContent>
        </Dialog>

        {/* Archive Account Dialog */}
        <Dialog open={showArchiveDialog} onOpenChange={setShowArchiveDialog}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Archive Account</DialogTitle>
              <DialogDescription>
                Are you sure you want to archive {selectedAccount?.name}? 
                This will hide the account from active accounts, but it can be restored later.
              </DialogDescription>
            </DialogHeader>
            <div className="flex justify-end space-x-2">
              <Button variant="outline" onClick={() => setShowArchiveDialog(false)}>
                Cancel
              </Button>
              <Button 
                variant="destructive" 
                onClick={handleArchiveAccount}
                disabled={processing}
              >
                {processing ? 'Archiving...' : 'Archive Account'}
              </Button>
            </div>
          </DialogContent>
        </Dialog>
        </div>
      </div>
    </AppLayout>
  )
}

