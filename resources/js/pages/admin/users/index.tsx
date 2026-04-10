import { Head, router, usePage } from '@inertiajs/react';
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
import { index as usersIndex } from '@/routes/admin/users';
import { update as updateActivation } from '@/routes/admin/users/activation';
import { update as updateSecurityGroups } from '@/routes/admin/users/security-groups';
import type { User } from '@/types';

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

export default function UsersIndex({
    users,
    securityGroups,
}: {
    users: AccountUser[];
    securityGroups: SecurityGroup[];
}) {
    const { auth } = usePage().props;
    const currentUserId = auth.user.id;

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
            updateSecurityGroups(editingUser.id).url,
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
            updateActivation(user.id).url,
            { deactivated: !user.deactivated_at },
            { preserveScroll: true },
        );
    }

    function userGroups(user: AccountUser): string[] {
        return user.security_group_memberships.map((m) => m.security_group);
    }

    const isEditingSelf = editingUser?.id === currentUserId;

    return (
        <>
            <Head title="Users" />

            <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b text-left">
                            <th className="px-4 py-3 font-medium">Name</th>
                            <th className="px-4 py-3 font-medium">Email</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Groups</th>
                            <th className="px-4 py-3 font-medium">Created</th>
                            <th className="px-4 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.map((user) => (
                            <tr
                                key={user.id}
                                className={`border-b last:border-0 ${user.deactivated_at ? 'opacity-50' : ''}`}
                            >
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
                                                    (sg) => sg.value === group,
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
                                        {user.id !== currentUserId && (
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
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
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
                        {securityGroups.map((group) => {
                            const isAdminGroupOnSelf =
                                isEditingSelf && group.value === 'admin';

                            return (
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
                                        disabled={isAdminGroupOnSelf}
                                    />
                                    <div className="grid gap-0.5">
                                        <Label htmlFor={`group-${group.value}`}>
                                            {group.label}
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            {isAdminGroupOnSelf
                                                ? 'You cannot remove your own Admin group.'
                                                : group.description}
                                        </p>
                                    </div>
                                </div>
                            );
                        })}
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

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: usersIndex() }],
};
