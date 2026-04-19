import type { Customer } from './customer';
import type { Estimate } from './estimate';

export type Project = {
    id: number;
    account_id: number;
    customer_id: number;
    name: string;
    site_address_line_1: string | null;
    site_address_line_2: string | null;
    site_city: string | null;
    site_state: string | null;
    site_zip: string | null;
    notes: string | null;
    last_activity_at: string;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    estimates_count?: number;
    customer?: Customer;
    estimates?: Estimate[];
};

export type ProjectListItem = Project & {
    customer: Pick<
        Customer,
        'id' | 'first_name' | 'last_name' | 'company' | 'address_line_1'
    >;
};

export type PaginatedProjects = {
    data: ProjectListItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type ProjectEventActorType = 'contractor' | 'customer' | 'system';

export type ProjectEventListItem = {
    id: number;
    event: string;
    event_label: string;
    actor_type: ProjectEventActorType;
    actor_name: string | null;
    estimate_id: number | null;
    estimate_title: string | null;
    estimate_deleted: boolean;
    metadata: Record<string, unknown> | null;
    created_at: string;
};
