import type { Auth } from './auth';

export type SharedData = {
    name: string;
    auth: Auth;
    flash?: {
        success?: string | null;
        error?: string | null;
    };
    sidebarOpen: boolean;
    [key: string]: unknown;
};

export type * from './auth';
export type * from './navigation';
export type * from './ui';
