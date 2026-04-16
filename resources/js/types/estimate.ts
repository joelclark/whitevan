import type { Customer } from './customer';

export type EstimateStatus = 'processing' | 'ready' | 'failed';

export type FloorplanAssetsStatus = 'pending' | 'ready' | 'failed';

export type FloorplanPagePreview = {
    page: number;
    width: number;
    height: number;
    url: string;
};

export type Trade = 'flooring';

export type InterviewAnswers = {
    rooms: Record<string, Record<string, string | number>>;
    project_wide: Record<string, string | number>;
};

export type QuestionShape = {
    key: string;
    label: string;
    help: string | null;
    type: 'select' | 'count';
    options: Record<string, string>;
    count_min: number;
    count_max: number;
};

export type PendingQuestionShape = QuestionShape & {
    phase: 'room' | 'project_wide' | 'done';
    room_id: number | null;
    room_name: string | null;
    room_index: number;
    total_rooms: number;
};

export type InterviewCatalog = {
    room: QuestionShape[];
    project_wide: QuestionShape[];
};

export type InterviewProps = {
    next_question: PendingQuestionShape | null;
    is_complete: boolean;
    catalog: InterviewCatalog;
};

export type EstimateRoom = {
    id: number;
    estimate_id: number;
    name: string;
    page: number;
    sqft: number;
    linear_feet: number;
    position: number;
    created_at: string;
    updated_at: string;
};

export type Estimate = {
    id: number;
    account_id: number;
    customer_id: number;
    trade: Trade;
    title: string | null;
    pdf_path: string;
    pdf_original_filename: string;
    total_sqft: number | null;
    status: EstimateStatus;
    floorplan_assets_status: FloorplanAssetsStatus | null;
    interview_answers: InterviewAnswers;
    line_item_prices: Record<string, number | null>;
    agent_errors: string[];
    debug_log: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    customer?: Customer;
    rooms?: EstimateRoom[];
};

export type EstimateListItem = Estimate & {
    customer: Pick<Customer, 'id' | 'first_name' | 'last_name' | 'company'>;
};

export type PaginatedEstimates = {
    data: EstimateListItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type AiAgentSetting = {
    id: number;
    kind: string;
    label: string;
    description: string | null;
    system_prompt: string;
    created_at: string;
    updated_at: string;
};
