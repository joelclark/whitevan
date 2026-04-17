import type { Customer } from './customer';
import type { Project } from './project';

export type EstimateStatus = 'processing' | 'ready' | 'failed';

export type QuoteStatus = 'sent';

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

export type EstimateLineItem = {
    id: number;
    key: string;
    label: string;
    category: string;
    category_label: string;
    quantity: number;
    unit: string;
    unit_price: number | null;
    notes: string | null;
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
    project_id: number;
    trade: Trade;
    title: string | null;
    pdf_path: string;
    pdf_original_filename: string;
    total_sqft: number | null;
    status: EstimateStatus;
    quote_status: QuoteStatus | null;
    quote_token: string | null;
    quote_sent_at: string | null;
    quote_customer_viewed_at: string | null;
    floorplan_assets_status: FloorplanAssetsStatus | null;
    interview_answers: InterviewAnswers;
    line_items?: EstimateLineItem[];
    agent_errors: string[];
    debug_log: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    customer?: Customer;
    project?: Project;
    rooms?: EstimateRoom[];
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
