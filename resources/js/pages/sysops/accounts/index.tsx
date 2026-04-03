import { Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { index as sysopsAccountsIndex, show } from '@/routes/sysops/accounts';
import type { Account, User } from '@/types';

type AccountWithOwner = Account & {
    owner: Pick<User, 'id' | 'name' | 'email'>;
};

type PaginatedAccounts = {
    data: AccountWithOwner[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

export default function AccountsIndex({ accounts }: { accounts: PaginatedAccounts }) {
    return (
        <>
            <Head title="Accounts" />
            <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b text-left">
                            <th className="px-4 py-3 font-medium">ID</th>
                            <th className="px-4 py-3 font-medium">Name</th>
                            <th className="px-4 py-3 font-medium">Owner</th>
                            <th className="px-4 py-3 font-medium">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        {accounts.data.map((account) => (
                            <tr key={account.id} className="border-b last:border-0">
                                <td className="px-4 py-3">{account.id}</td>
                                <td className="px-4 py-3">
                                    <TextLink href={show(account.id).url}>{account.name}</TextLink>
                                </td>
                                <td className="px-4 py-3">{account.owner.name}</td>
                                <td className="px-4 py-3">{new Date(account.created_at).toLocaleDateString()}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}

AccountsIndex.layout = {
    breadcrumbs: [
        { title: 'Sysops', href: sysopsAccountsIndex().url },
        { title: 'Accounts', href: sysopsAccountsIndex().url },
    ],
};
