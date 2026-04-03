import { Head, setLayoutProps } from '@inertiajs/react';
import { index as sysopsAccountsIndex, show } from '@/routes/sysops/accounts';
import type { Account, User } from '@/types';

type AccountDetail = Account & {
    owner: Pick<User, 'id' | 'name' | 'email'>;
    users: Pick<User, 'id' | 'name' | 'email' | 'created_at'>[];
};

export default function AccountShow({ account }: { account: AccountDetail }) {
    setLayoutProps({
        title: account.name,
        description: 'Account details and users',
        breadcrumbs: [
            { title: 'Sysops', href: sysopsAccountsIndex().url },
            { title: 'Accounts', href: sysopsAccountsIndex().url },
            { title: account.name, href: show(account.id).url },
        ],
    });

    return (
        <>
            <Head title={account.name} />

            <div className="space-y-6">
                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <dl className="divide-y text-sm">
                        <div className="flex px-4 py-3">
                            <dt className="w-32 font-medium">ID</dt>
                            <dd>{account.id}</dd>
                        </div>
                        <div className="flex px-4 py-3">
                            <dt className="w-32 font-medium">Name</dt>
                            <dd>{account.name}</dd>
                        </div>
                        <div className="flex px-4 py-3">
                            <dt className="w-32 font-medium">Owner</dt>
                            <dd>
                                {account.owner.name} ({account.owner.email})
                            </dd>
                        </div>
                        <div className="flex px-4 py-3">
                            <dt className="w-32 font-medium">Created</dt>
                            <dd>
                                {new Date(
                                    account.created_at,
                                ).toLocaleDateString()}
                            </dd>
                        </div>
                        <div className="flex px-4 py-3">
                            <dt className="w-32 font-medium">Updated</dt>
                            <dd>
                                {new Date(
                                    account.updated_at,
                                ).toLocaleDateString()}
                            </dd>
                        </div>
                    </dl>
                </div>

                <h3 className="text-lg font-medium">Users</h3>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left">
                                <th className="px-4 py-3 font-medium">ID</th>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Email</th>
                                <th className="px-4 py-3 font-medium">
                                    Created
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {account.users.map((user) => (
                                <tr
                                    key={user.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-4 py-3">{user.id}</td>
                                    <td className="px-4 py-3">{user.name}</td>
                                    <td className="px-4 py-3">{user.email}</td>
                                    <td className="px-4 py-3">
                                        {new Date(
                                            user.created_at,
                                        ).toLocaleDateString()}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
