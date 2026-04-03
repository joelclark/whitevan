export type User = {
    id: number;
    account_id: number | null;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Account = {
    id: number;
    name: string;
    owner_user_id: number;
    created_at: string;
    updated_at: string;
};

export type Auth = {
    user: User;
    account: Account | null;
    is_sysop: boolean;
};

export type ActivityLog = {
    id: number;
    type: 'error' | 'info';
    description: string;
    metadata: Record<string, unknown> | null;
    account_id: number | null;
    user_id: number | null;
    created_at: string;
    account: Pick<Account, 'id' | 'name'> | null;
    user: Pick<User, 'id' | 'name' | 'email'> | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
