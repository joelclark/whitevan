import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { index as sysopsAccountsIndex, show } from '@/routes/sysops/accounts';
import { update as updateActivation } from '@/routes/sysops/accounts/users/activation';
import { update as updateSecurityGroups } from '@/routes/sysops/accounts/users/security-groups';
import type { Account, User } from '@/types';

type SecurityGroup = {
    value: string;
    label: string;
    description: string;
    abilities: string[];
};

type SecurityGroupMembership = {
    id: number;
    security_group: string;
};

type AccountUser = Pick<
    User,
    'id' | 'name' | 'email' | 'deactivated_at' | 'created_at'
> & {
    security_group_memberships: SecurityGroupMembership[];
};

type AccountDetail = Account & {
    owner: Pick<User, 'id' | 'name' | 'email'>;
    users: AccountUser[];
};

export default function AccountShow({
    account,
    securityGroups,
}: {
    account: AccountDetail;
    securityGroups: SecurityGroup[];
}) {
    setLayoutProps({
        title: account.name,
        description: 'Account details and users',
        breadcrumbs: [
            { title: 'Sysops', href: sysopsAccountsIndex().url },
            { title: 'Accounts', href: sysopsAccountsIndex().url },
            { title: account.name, href: show(account.id).url },
        ],
    });

    const [editingUser, setEditingUser] = useState<AccountUser | null>(null);
    const [selectedGroups, setSelectedGroups] = useState<string[]>([]);
    const [saving, setSaving] = useState(false);

    function openDialog(user: AccountUser) {
        setEditingUser(user);
        setSelectedGroups(
            user.security_group_memberships.map((m) => m.security_group),
        );
    }

    function toggleGroup(value: string) {
        setSelectedGroups((prev) =>
            prev.includes(value)
                ? prev.filter((g) => g !== value)
                : [...prev, value],
        );
    }

    function saveGroups() {
        if (!editingUser) {
            return;
        }

        setSaving(true);
        router.put(
            updateSecurityGroups([account.id, editingUser.id]).url,
            { security_groups: selectedGroups },
            {
                preserveScroll: true,
                onSuccess: () => setEditingUser(null),
                onFinish: () => setSaving(false),
            },
        );
    }

    function toggleActivation(user: AccountUser) {
        router.put(
            updateActivation([account.id, user.id]).url,
            { deactivated: !user.deactivated_at },
            { preserveScroll: true },
        );
    }

    function userGroups(user: AccountUser): string[] {
        return user.security_group_memberships.map((m) => m.security_group);
    }

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
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Groups
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Created
                                </th>
                                <th className="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {account.users.map((user) => (
                                <tr
                                    key={user.id}
                                    className={`border-b last:border-0 ${user.deactivated_at ? 'opacity-50' : ''}`}
                                >
                                    <td className="px-4 py-3">{user.id}</td>
                                    <td className="px-4 py-3">{user.name}</td>
                                    <td className="px-4 py-3">{user.email}</td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                user.deactivated_at
                                                    ? 'destructive'
                                                    : 'secondary'
                                            }
                                        >
                                            {user.deactivated_at
                                                ? 'Deactivated'
                                                : 'Active'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex gap-1">
                                            {userGroups(user).map((group) => (
                                                <Badge
                                                    key={group}
                                                    variant="secondary"
                                                >
                                                    {securityGroups.find(
                                                        (sg) =>
                                                            sg.value === group,
                                                    )?.label ?? group}
                                                </Badge>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {new Date(
                                            user.created_at,
                                        ).toLocaleDateString()}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => openDialog(user)}
                                            >
                                                Edit groups
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    toggleActivation(user)
                                                }
                                            >
                                                {user.deactivated_at
                                                    ? 'Activate'
                                                    : 'Deactivate'}
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog
                open={editingUser !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditingUser(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Security groups for {editingUser?.name}
                        </DialogTitle>
                        <DialogDescription>
                            Select the security groups this user belongs to.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3 py-2">
                        {securityGroups.map((group) => (
                            <div
                                key={group.value}
                                className="flex items-start gap-3"
                            >
                                <Checkbox
                                    id={`group-${group.value}`}
                                    checked={selectedGroups.includes(
                                        group.value,
                                    )}
                                    onCheckedChange={() =>
                                        toggleGroup(group.value)
                                    }
                                />
                                <div className="grid gap-0.5">
                                    <Label htmlFor={`group-${group.value}`}>
                                        {group.label}
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        {group.description}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setEditingUser(null)}
                        >
                            Cancel
                        </Button>
                        <Button onClick={saveGroups} disabled={saving}>
                            {saving ? 'Saving...' : 'Save'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
