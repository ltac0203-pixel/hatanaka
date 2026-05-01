import Index from "@/Pages/Subscription/Index";
import { act, fireEvent, render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";

const { mockDelete } = vi.hoisted(() => ({
    mockDelete: vi.fn(),
}));

vi.mock("@inertiajs/react", () => ({
    Head: ({ title }: { title: string }) => (
        <div data-testid="head-title">{title}</div>
    ),
    router: {
        delete: mockDelete,
    },
}));

vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    default: ({ children }: { children: ReactNode }) => <div>{children}</div>,
}));

vi.mock("@/Components/ActionLink", () => ({
    default: ({
        children,
        href,
    }: {
        children: ReactNode;
        href: string;
    }) => <a href={href}>{children}</a>,
}));

vi.mock("@/Components/TextMark", () => ({
    default: ({ label }: { label: string }) => <span>{label}</span>,
}));

vi.mock("@/Components/Modal", () => ({
    default: ({
        children,
        show,
    }: {
        children: ReactNode;
        show: boolean;
    }) => (show ? <div role="dialog">{children}</div> : null),
}));

vi.mock("@/utils/routes", () => ({
    appRoutes: {
        plans: {
            index: () => "/plans",
        },
        subscription: {
            destroy: (subscriptionId: number) =>
                `/subscription/${subscriptionId}`,
        },
        cards: {
            create: () => "/cards/create",
            destroy: (cardId: number) => `/cards/${cardId}`,
        },
    },
}));

const subscription = {
    id: 1,
    fincode_subscription_id: "sub_test_123",
    status: "active" as const,
    start_date: "2026-03-01",
    stop_date: null,
    next_charge_date: "2026-04-01",
    canceled_at: null,
    plan: {
        id: "plan_1",
        fincode_plan_id: "plan_1",
        name: "Standard",
        description: null,
        amount: 1000,
        interval: "monthly",
        interval_count: 1,
        status: "active" as const,
        features: null,
        price_display: "¥1,000/月",
        interval_label: "月",
    },
    card: {
        id: 10,
        brand: "Visa",
        last4: "4242",
        exp_month: 12,
        exp_year: 2030,
        holder_name: "TEST USER",
        is_default: true,
        is_expired: false,
        display_name: "Visa **** 4242",
        expiry_display: "12/30",
    },
};

const cards = [
    {
        id: 10,
        brand: "Visa",
        last4: "4242",
        exp_month: 12,
        exp_year: 2030,
        holder_name: "TEST USER",
        is_default: true,
        is_expired: false,
        display_name: "Visa **** 4242",
        expiry_display: "12/30",
    },
];

const pageProps = {
    auth: {
        user: {
            id: 1,
            name: "Test User",
            email: "test@example.com",
        },
    },
    flash: {
        key: null,
        success: null,
        error: null,
    },
};

afterEach(() => {
    mockDelete.mockReset();
});

function renderPage() {
    render(
        <Index
            {...pageProps}
            subscription={subscription}
            cards={cards}
        />,
    );
}

function getLastDeleteOptions() {
    const lastCall = mockDelete.mock.lastCall;

    if (!lastCall) {
        throw new Error("router.delete was not called");
    }

    return lastCall[1] as {
        onError?: (errors: Record<string, string | string[] | undefined>) => void;
        onFinish?: () => void;
        onSuccess?: () => void;
    };
}

describe("Subscription index page", () => {
    it("keeps the cancel dialog open and shows an error after a failed cancellation", async () => {
        renderPage();

        fireEvent.click(
            screen.getByRole("button", { name: "サブスクリプションを解約" }),
        );
        fireEvent.click(screen.getByRole("button", { name: "解約する" }));

        expect(mockDelete).toHaveBeenCalledWith(
            "/subscription/1",
            expect.objectContaining({
                preserveScroll: true,
            }),
        );
        expect(
            screen.getByRole("button", { name: "解約する" }),
        ).toBeDisabled();

        const options = getLastDeleteOptions();

        await act(async () => {
            options.onError?.({
                subscription: "サブスクリプションの解約に失敗しました。",
            });
            options.onFinish?.();
        });

        expect(screen.getByText("サブスクリプション解約")).toBeInTheDocument();
        expect(
            screen.getByText("サブスクリプションの解約に失敗しました。"),
        ).toBeInTheDocument();
        expect(
            screen.getByRole("button", { name: "解約する" }),
        ).not.toBeDisabled();

        fireEvent.click(screen.getByRole("button", { name: "キャンセル" }));
        expect(
            screen.queryByText("サブスクリプションの解約に失敗しました。"),
        ).not.toBeInTheDocument();

        fireEvent.click(
            screen.getByRole("button", { name: "サブスクリプションを解約" }),
        );
        expect(
            screen.queryByText("サブスクリプションの解約に失敗しました。"),
        ).not.toBeInTheDocument();
    });

    it("closes the cancel dialog after a successful cancellation", async () => {
        renderPage();

        fireEvent.click(
            screen.getByRole("button", { name: "サブスクリプションを解約" }),
        );
        fireEvent.click(screen.getByRole("button", { name: "解約する" }));

        const options = getLastDeleteOptions();

        await act(async () => {
            options.onSuccess?.();
            options.onFinish?.();
        });

        expect(
            screen.queryByText("サブスクリプション解約"),
        ).not.toBeInTheDocument();
    });

    it("keeps the card deletion dialog open and shows an error after failure", async () => {
        renderPage();

        fireEvent.click(screen.getByRole("button", { name: "削除" }));
        fireEvent.click(screen.getByRole("button", { name: "削除する" }));

        expect(mockDelete).toHaveBeenCalledWith(
            "/cards/10",
            expect.objectContaining({
                preserveScroll: true,
            }),
        );

        const options = getLastDeleteOptions();

        await act(async () => {
            options.onError?.({
                card: "アクティブなサブスクリプションで使用中のカードは削除できません。",
            });
            options.onFinish?.();
        });

        expect(screen.getByText("カード削除")).toBeInTheDocument();
        expect(
            screen.getByText(
                "アクティブなサブスクリプションで使用中のカードは削除できません。",
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole("button", { name: "削除する" }),
        ).not.toBeDisabled();
    });
});
