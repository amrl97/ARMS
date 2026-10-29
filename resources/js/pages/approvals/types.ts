export type ApprovalInboxItem = {
    id: number;
    requestId: number;
    step: 'module' | 'final';
    module: string;
    accessType: string;
    priority: 'Low' | 'Medium' | 'High';
    justification: string | null;
    startDate: string | null;
    endDate: string | null;
    requester: string;
    beneficiary: string;
    submittedAt: string | null;
};

export type ApprovalQueue = {
    data: ApprovalInboxItem[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
};
