export type Customer = {
    id: number;
    account_id: number;
    first_name: string;
    last_name: string;
    company: string | null;
    email: string | null;
    phone: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    state: string | null;
    zip: string | null;
    notes: string | null;
    last_accessed_at: string | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    estimates_count?: number;
};

export type PaginatedCustomers = {
    data: Customer[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
