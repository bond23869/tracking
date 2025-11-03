import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useIsMobile } from '@/hooks/use-mobile';
import { type SharedData, type Account } from '@/types';
import { usePage, router } from '@inertiajs/react';
import { ChevronsUpDown, Plus } from 'lucide-react';

export function AccountSelector() {
    const { currentAccount, accounts: allAccounts } = usePage<SharedData>().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();

    const handleSwitchAccount = (account: Account) => {
        router.post(`/accounts/${account.id}/switch`, {}, {
            preserveScroll: true,
            preserveState: false,
        });
    };

    const handleCreateAccount = () => {
        router.visit('/accounts');
    };

    if (!currentAccount) {
        return null;
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="group text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
                            data-test="account-selector-button"
                        >
                            <div className="flex flex-col items-start gap-1">
                                <span className="text-xs text-muted-foreground">Account</span>
                                <span className="font-semibold">{currentAccount.name}</span>
                            </div>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="end"
                        side={
                            isMobile
                                ? 'bottom'
                                : state === 'collapsed'
                                  ? 'left'
                                  : 'bottom'
                        }
                    >
                        {allAccounts.length > 0 && (
                            <>
                                {allAccounts.map((account) => (
                                    <DropdownMenuItem
                                        key={account.id}
                                        onClick={() => handleSwitchAccount(account)}
                                        className={account.id === currentAccount.id ? 'bg-muted' : ''}
                                    >
                                        <span className="flex-1">{account.name}</span>
                                        {account.id === currentAccount.id && (
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                Current
                                            </span>
                                        )}
                                    </DropdownMenuItem>
                                ))}
                                <DropdownMenuSeparator />
                            </>
                        )}
                        <DropdownMenuItem onClick={handleCreateAccount}>
                            <Plus className="mr-2 h-4 w-4" />
                            <span>Create a new account</span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

