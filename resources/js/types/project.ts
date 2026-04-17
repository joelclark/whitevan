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
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    estimates_count?: number;
    customer?: Customer;
    estimates?: Estimate[];
};
