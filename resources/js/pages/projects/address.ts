import type { Customer, Project } from '@/types';

type AddressFields = Pick<
    Customer,
    'address_line_1' | 'address_line_2' | 'city' | 'state' | 'zip'
>;

type AddressableProject = Project & {
    customer?: Partial<AddressFields> | null;
};

type TitleableProject = AddressableProject & {
    customer?: (Partial<AddressFields> & Pick<Customer, 'last_name'>) | null;
};

export type ResolvedAddress = {
    [K in keyof AddressFields]: string | null;
};

export function resolveProjectAddress(
    project: AddressableProject,
): ResolvedAddress {
    if (project.site_address_line_1) {
        return {
            address_line_1: project.site_address_line_1,
            address_line_2: project.site_address_line_2,
            city: project.site_city,
            state: project.site_state,
            zip: project.site_zip,
        };
    }

    const customer = project.customer;

    return {
        address_line_1: customer?.address_line_1 ?? null,
        address_line_2: customer?.address_line_2 ?? null,
        city: customer?.city ?? null,
        state: customer?.state ?? null,
        zip: customer?.zip ?? null,
    };
}

export function buildProjectTitle(project: TitleableProject): string {
    const street = resolveProjectAddress(project).address_line_1;

    return [project.name, project.customer?.last_name, street]
        .filter((part): part is string => Boolean(part && part.trim()))
        .join(' / ');
}

export function formatProjectAddress(
    project: AddressableProject,
): string | null {
    const source = resolveProjectAddress(project);
    const parts: string[] = [];

    if (source.address_line_1) {
        parts.push(source.address_line_1);
    }

    if (source.address_line_2) {
        parts.push(source.address_line_2);
    }

    const cityStateZip = [source.city, source.state, source.zip]
        .filter((p): p is string => Boolean(p && p.trim()))
        .join(' ');

    if (cityStateZip) {
        parts.push(cityStateZip);
    }

    return parts.length > 0 ? parts.join(', ') : null;
}
