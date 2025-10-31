import { Head, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/layouts/app-layout'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import AlertError from '@/components/alert-error'
import { Icon } from '@/components/icon'
import { formatDistanceToNow } from 'date-fns'
import { UserPlus, MoreHorizontal, Edit, UserMinus, Mail, Copy, X } from 'lucide-react'

interface User {
  id: number
  name: string
  email: string
  avatar?: string
  role: string
  last_login_at?: string
  created_at: string
  is_current_user: boolean
}

interface Invitation {
  id: number
  email: string
  role: string
  invited_by: {
    name: string
    email: string
  }
  expires_at: string
  created_at: string
  invitation_url: string
}

interface PageProps {
  teamMembers: User[]
  pendingInvitations: Invitation[]
  canManageTeam: boolean
  errors?: Record<string, string>
  success?: string
}

export default function UsersIndex() {
  const { teamMembers, pendingInvitations, canManageTeam, errors, success } = usePage<PageProps>().props
  const [showInviteDialog, setShowInviteDialog] = useState(false)
  const [showEditDialog, setShowEditDialog] = useState(false)
  const [showRemoveDialog, setShowRemoveDialog] = useState(false)
  const [selectedUser, setSelectedUser] = useState<User | null>(null)
  const [inviteForm, setInviteForm] = useState({ email: '', role: 'member' })
  const [editForm, setEditForm] = useState({ name: '', role: 'member' })
  const [processing, setProcessing] = useState(false)

  const handleInviteUser = (e: React.FormEvent) => {
    e.preventDefault()
    setProcessing(true)
    
    router.post('/invitations', inviteForm, {
      onSuccess: () => {
        setShowInviteDialog(false)
        setInviteForm({ email: '', role: 'member' })
      },
      onFinish: () => setProcessing(false)
    })
  }

  const handleEditUser = (e: React.FormEvent) => {
    e.preventDefault()
    if (!selectedUser) return
    
    setProcessing(true)
    
    router.patch(`/users/${selectedUser.id}`, editForm, {
      onSuccess: () => {
        setShowEditDialog(false)
        setSelectedUser(null)
      },
      onFinish: () => setProcessing(false)
    })
  }

  const handleRemoveUser = () => {
    if (!selectedUser) return
    
    setProcessing(true)
    
    router.delete(`/users/${selectedUser.id}`, {
      onSuccess: () => {
        setShowRemoveDialog(false)
        setSelectedUser(null)
      },
      onFinish: () => setProcessing(false)
    })
  }

  const handleResendInvitation = (invitationId: number) => {
    router.post(`/invitations/${invitationId}/resend`)
  }

  const handleCancelInvitation = (invitationId: number) => {
    router.delete(`/invitations/${invitationId}`)
  }

  const openEditDialog = (user: User) => {
    setSelectedUser(user)
    setEditForm({ name: user.name, role: user.role })
    setShowEditDialog(true)
  }

  const openRemoveDialog = (user: User) => {
    setSelectedUser(user)
    setShowRemoveDialog(true)
  }

  const getRoleColor = (role: string) => {
    switch (role) {
      case 'admin':
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
      case 'member':
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300'
      default:
        return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300'
    }
  }

  const getInitials = (name: string) => {
    return name
      .split(' ')
      .map(n => n[0])
      .join('')
      .toUpperCase()
      .slice(0, 2)
  }

  return (
    <AppLayout>
      <Head title="Team Members" />
      
      <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Team Members</h1>
            <p className="text-muted-foreground">
              Manage your organization's team members and invitations
            </p>
          </div>
          
          {canManageTeam && (
            <Dialog open={showInviteDialog} onOpenChange={setShowInviteDialog}>
              <DialogTrigger asChild>
                <Button>
                  <Icon iconNode={UserPlus} className="mr-2 h-4 w-4" />
                  Invite Member
                </Button>
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>Invite Team Member</DialogTitle>
                  <DialogDescription>
                    Send an invitation to join your organization
                  </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleInviteUser} className="space-y-4">
                  <div>
                    <Label htmlFor="email">Email Address</Label>
                    <Input
                      id="email"
                      type="email"
                      value={inviteForm.email}
                      onChange={(e) => setInviteForm({ ...inviteForm, email: e.target.value })}
                      placeholder="Enter email address"
                      required
                    />
                  </div>
                  <div>
                    <Label htmlFor="role">Role</Label>
                    <Select value={inviteForm.role} onValueChange={(value) => setInviteForm({ ...inviteForm, role: value })}>
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="member">Member</SelectItem>
                        <SelectItem value="admin">Admin</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="flex justify-end space-x-2">
                    <Button type="button" variant="outline" onClick={() => setShowInviteDialog(false)}>
                      Cancel
                    </Button>
                    <Button type="submit" disabled={processing}>
                      {processing ? 'Sending...' : 'Send Invitation'}
                    </Button>
                  </div>
                </form>
              </DialogContent>
            </Dialog>
          )}
        </div>

        {success && (
          <div className="rounded-md bg-green-50 p-4 dark:bg-green-900/20">
            <div className="text-sm text-green-700 dark:text-green-300">{success}</div>
          </div>
        )}

        {errors && Object.keys(errors).length > 0 && (
          <AlertError errors={Object.values(errors)} />
        )}

        {/* Team Members */}
        <Card>
          <CardHeader>
            <CardTitle>Active Members ({teamMembers.length})</CardTitle>
            <CardDescription>
              Current team members in your organization
            </CardDescription>
          </CardHeader>
          <CardContent>
            {teamMembers.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-12 text-center">
                <div className="rounded-full bg-muted p-3 mb-4">
                  <Icon iconNode={UserPlus} className="h-6 w-6 text-muted-foreground" />
                </div>
                <h3 className="text-lg font-medium mb-2">No team members yet</h3>
                <p className="text-muted-foreground mb-4">
                  Start building your team by inviting members to your organization.
                </p>
                {canManageTeam && (
                  <Dialog open={showInviteDialog} onOpenChange={setShowInviteDialog}>
                    <DialogTrigger asChild>
                      <Button>
                        <Icon iconNode={UserPlus} className="mr-2 h-4 w-4" />
                        Invite Your First Member
                      </Button>
                    </DialogTrigger>
                  </Dialog>
                )}
              </div>
            ) : (
              <div className="rounded-md border">
                <table className="w-full">
                  <thead>
                    <tr className="border-b bg-muted/50">
                      <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                        Member
                      </th>
                      <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                        Role
                      </th>
                      <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                        Joined
                      </th>
                      <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                        Status
                      </th>
                      {canManageTeam && (
                        <th className="h-12 px-4 text-right align-middle font-medium text-muted-foreground">
                          Actions
                        </th>
                      )}
                    </tr>
                  </thead>
                  <tbody>
                    {teamMembers.map((user) => (
                      <tr key={user.id} className="border-b transition-colors hover:bg-muted/50">
                        <td className="p-4 align-middle">
                          <div className="flex items-center space-x-3">
                            <Avatar className="h-8 w-8">
                              <AvatarImage src={user.avatar} />
                              <AvatarFallback className="text-xs">{getInitials(user.name)}</AvatarFallback>
                            </Avatar>
                            <div>
                              <div className="font-medium">{user.name}</div>
                              <div className="text-sm text-muted-foreground">{user.email}</div>
                            </div>
                          </div>
                        </td>
                        <td className="p-4 align-middle">
                          <Badge className={getRoleColor(user.role)}>
                            {user.role}
                          </Badge>
                        </td>
                        <td className="p-4 align-middle">
                          <div className="text-sm">
                            {formatDistanceToNow(new Date(user.created_at), { addSuffix: true })}
                          </div>
                        </td>
                        <td className="p-4 align-middle">
                          {user.is_current_user ? (
                            <Badge variant="secondary">You</Badge>
                          ) : (
                            <span className="text-sm text-muted-foreground">Active</span>
                          )}
                        </td>
                        {canManageTeam && (
                          <td className="p-4 align-middle text-right">
                            {!user.is_current_user && (
                              <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                  <Button variant="ghost" size="sm">
                                    <Icon iconNode={MoreHorizontal} className="h-4 w-4" />
                                  </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                  <DropdownMenuItem onClick={() => openEditDialog(user)}>
                                    <Icon iconNode={Edit} className="mr-2 h-4 w-4" />
                                    Edit Details
                                  </DropdownMenuItem>
                                  <DropdownMenuSeparator />
                                  <DropdownMenuItem 
                                    onClick={() => openRemoveDialog(user)}
                                    className="text-red-600 dark:text-red-400"
                                  >
                                    <Icon iconNode={UserMinus} className="mr-2 h-4 w-4" />
                                    Remove from Team
                                  </DropdownMenuItem>
                                </DropdownMenuContent>
                              </DropdownMenu>
                            )}
                          </td>
                        )}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Pending Invitations */}
        {pendingInvitations.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle>Pending Invitations ({pendingInvitations.length})</CardTitle>
              <CardDescription>
                Invitations that haven't been accepted yet
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="rounded-md border">
                <table className="w-full">
                  <thead>
                    <tr className="border-b bg-muted/50">
                      <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                        Email
                      </th>
                      <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                        Role
                      </th>
                      <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                        Invited By
                      </th>
                      <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground">
                        Expires
                      </th>
                      {canManageTeam && (
                        <th className="h-12 px-4 text-right align-middle font-medium text-muted-foreground">
                          Actions
                        </th>
                      )}
                    </tr>
                  </thead>
                  <tbody>
                    {pendingInvitations.map((invitation) => (
                      <tr key={invitation.id} className="border-b transition-colors hover:bg-muted/50">
                        <td className="p-4 align-middle">
                          <div className="flex items-center space-x-3">
                            <Avatar className="h-8 w-8">
                              <AvatarFallback>
                                <Icon iconNode={Mail} className="h-4 w-4" />
                              </AvatarFallback>
                            </Avatar>
                            <div className="font-medium">{invitation.email}</div>
                          </div>
                        </td>
                        <td className="p-4 align-middle">
                          <Badge className={getRoleColor(invitation.role)}>
                            {invitation.role}
                          </Badge>
                        </td>
                        <td className="p-4 align-middle">
                          <div className="text-sm">{invitation.invited_by.name}</div>
                        </td>
                        <td className="p-4 align-middle">
                          <div className="text-sm">
                            {formatDistanceToNow(new Date(invitation.expires_at), { addSuffix: true })}
                          </div>
                        </td>
                        {canManageTeam && (
                          <td className="p-4 align-middle text-right">
                            <DropdownMenu>
                              <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="sm">
                                  <Icon iconNode={MoreHorizontal} className="h-4 w-4" />
                                </Button>
                              </DropdownMenuTrigger>
                              <DropdownMenuContent align="end">
                                <DropdownMenuItem onClick={() => handleResendInvitation(invitation.id)}>
                                  <Icon iconNode={Mail} className="mr-2 h-4 w-4" />
                                  Resend Invitation
                                </DropdownMenuItem>
                                <DropdownMenuItem 
                                  onClick={() => navigator.clipboard.writeText(invitation.invitation_url)}
                                >
                                  <Icon iconNode={Copy} className="mr-2 h-4 w-4" />
                                  Copy Link
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem 
                                  onClick={() => handleCancelInvitation(invitation.id)}
                                  className="text-red-600 dark:text-red-400"
                                >
                                  <Icon iconNode={X} className="mr-2 h-4 w-4" />
                                  Cancel Invitation
                                </DropdownMenuItem>
                              </DropdownMenuContent>
                            </DropdownMenu>
                          </td>
                        )}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Edit User Dialog */}
        <Dialog open={showEditDialog} onOpenChange={setShowEditDialog}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Edit Team Member</DialogTitle>
              <DialogDescription>
                Update the member's name and role
              </DialogDescription>
            </DialogHeader>
            <form onSubmit={handleEditUser} className="space-y-4">
              <div>
                <Label htmlFor="edit-name">Name</Label>
                <Input
                  id="edit-name"
                  value={editForm.name}
                  onChange={(e) => setEditForm({ ...editForm, name: e.target.value })}
                  required
                />
              </div>
              <div>
                <Label htmlFor="edit-role">Role</Label>
                <Select value={editForm.role} onValueChange={(value) => setEditForm({ ...editForm, role: value })}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="member">Member</SelectItem>
                    <SelectItem value="admin">Admin</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="flex justify-end space-x-2">
                <Button type="button" variant="outline" onClick={() => setShowEditDialog(false)}>
                  Cancel
                </Button>
                <Button type="submit" disabled={processing}>
                  {processing ? 'Updating...' : 'Update Member'}
                </Button>
              </div>
            </form>
          </DialogContent>
        </Dialog>

        {/* Remove User Dialog */}
        <Dialog open={showRemoveDialog} onOpenChange={setShowRemoveDialog}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Remove Team Member</DialogTitle>
              <DialogDescription>
                Are you sure you want to remove {selectedUser?.name} from your organization?
                This action cannot be undone.
              </DialogDescription>
            </DialogHeader>
            <div className="flex justify-end space-x-2">
              <Button variant="outline" onClick={() => setShowRemoveDialog(false)}>
                Cancel
              </Button>
              <Button 
                variant="destructive" 
                onClick={handleRemoveUser}
                disabled={processing}
              >
                {processing ? 'Removing...' : 'Remove Member'}
              </Button>
            </div>
          </DialogContent>
        </Dialog>
        </div>
      </div>
    </AppLayout>
  )
}
