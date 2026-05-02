export interface User {
    id: number;
    name: string;
    email: string;
}

export interface FlashMessages {
    key: string | null;
    success: string | null;
    error: string | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User | null;
    };
    flash: FlashMessages;
};
