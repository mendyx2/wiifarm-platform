import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    [key: string]: unknown;
}

export interface User {
    id: number;
    username: string;
    first_name: string;
    last_name: string;
    /** first and last name together */
    name: string;
    email: string;
    dial_code: string;
    mobile: string;
    country: string;
    state: string | null;
    city: string | null;
    address: string | null;
    sponsor_id: number | null;
    status: 'active' | 'banned';
    activated_at: string | null;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
