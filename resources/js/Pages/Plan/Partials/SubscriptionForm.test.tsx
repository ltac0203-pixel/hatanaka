import SubscriptionForm from "@/Pages/Plan/Partials/SubscriptionForm";
import { t } from "@/i18n";
import type { FincodeCard, Plan } from "@/types/subscription";
import { act, fireEvent, render, screen } from "@testing-library/react";
import { useState, type ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const { postMock, setDataMock, useFormMock } = vi.hoisted(() => ({
    postMock: vi.fn(),
    setDataMock: vi.fn(),
    useFormMock: vi.fn(),
}));

vi.mock("@inertiajs/react", () => ({
    Link: ({
        children,
        href,
        className,
    }: {
        children: ReactNode;
        href: string;
        className?: string;
    }) => (
        <a href={href} className={className}>
            {children}
        </a>
    ),
    // post() の呼び出し中だけ processing=true を返す可変モック。
    // SubscriptionForm 側が独自 isSubmitting state を捨てて useForm.processing
    // のみで disabled 制御するようになったため、テスト側もそれに追随する。
    useForm: (initialData: {
        fincode_plan_id: string;
        card_id: number;
        start_date: string;
    }) => {
        useFormMock(initialData);
        const [processing, setProcessing] = useState(false);

        const post = (
            url: string,
            options: {
                onError?: (errors: Record<string, string | string[] | undefined>) => void;
                onFinish?: () => void;
            } = {},
        ) => {
            setProcessing(true);
            const wrappedOptions = {
                ...options,
                onFinish: () => {
                    setProcessing(false);
                    options.onFinish?.();
                },
            };
            postMock(url, wrappedOptions);
        };

        return {
            data: initialData,
            setData: setDataMock,
            post,
            processing,
            errors: {},
        };
    },
}));

describe("SubscriptionForm", () => {
    const routeMock = vi.fn((name: string) => `/${name}`);

    const plan: Plan = {
        id: "pl_test_plan",
        fincode_plan_id: "pl_test_plan",
        name: "Test Plan",
        description: null,
        amount: 1000,
        interval: "monthly",
        interval_count: 1,
        status: "active",
        features: null,
        price_display: "¥1,000/月",
        interval_label: "月",
    };

    const cards: readonly [FincodeCard, ...FincodeCard[]] = [
        {
            id: 1,
            brand: "Visa",
            last4: "4242",
            exp_month: 12,
            exp_year: 2030,
            holder_name: "TEST USER",
            is_default: true,
            is_expired: false,
            display_name: "Visa ending in 4242",
            expiry_display: "12/30",
        },
    ];

    beforeEach(() => {
        postMock.mockClear();
        setDataMock.mockClear();
        useFormMock.mockClear();
        routeMock.mockClear();
        vi.stubGlobal("route", routeMock);
    });

    function getForm() {
        const button = screen.getByRole("button", {
            name: t("subscriptionForm.registerButton"),
        });
        const form = button.closest("form");

        if (!form) {
            throw new Error("subscription form was not rendered");
        }

        return { button, form };
    }

    function getLastPostOptions() {
        const lastCall = postMock.mock.lastCall;

        if (!lastCall) {
            throw new Error("post was not called");
        }

        return lastCall[1] as {
            onError?: (errors: Record<string, string | string[] | undefined>) => void;
            onFinish?: () => void;
        };
    }

    it("uses the server minimum date for the initial start date and browser guard", () => {
        const minimumStartDate = "2026-03-08";

        render(
            <SubscriptionForm
                plan={plan}
                cards={cards}
                minimumStartDate={minimumStartDate}
            />,
        );

        expect(useFormMock).toHaveBeenCalledWith(
            expect.objectContaining({
                card_id: 1,
                start_date: minimumStartDate,
            }),
        );

        const startDateInput = screen.getByLabelText(
            t("subscriptionForm.startDateLabel"),
        );

        expect(startDateInput).toHaveValue(minimumStartDate);
        expect(startDateInput).toHaveAttribute("min", minimumStartDate);
        expect(
            screen.getByRole("link", {
                name: t("subscriptionForm.backToPlans"),
            }),
        ).toHaveAttribute("href", "/plans.index");
    });

    it("falls back to the first card when no default card exists", () => {
        const nonDefaultCards: FincodeCard[] = [
            {
                ...cards[0],
                id: 7,
                is_default: false,
            },
            {
                ...cards[0],
                id: 8,
                is_default: false,
                display_name: "Mastercard ending in 4444",
            },
        ];

        render(
            <SubscriptionForm
                plan={plan}
                cards={nonDefaultCards}
                minimumStartDate="2026-03-08"
            />,
        );

        expect(useFormMock).toHaveBeenCalledWith(
            expect.objectContaining({
                card_id: 7,
            }),
        );
    });

    it("submits with explicit error and finish handlers and releases loading state on finish", async () => {
        render(
            <SubscriptionForm
                plan={plan}
                cards={cards}
                minimumStartDate="2026-03-08"
            />,
        );

        const { button, form } = getForm();

        fireEvent.submit(form);

        expect(postMock).toHaveBeenCalledWith(
            "/subscription.store",
            expect.objectContaining({
                onError: expect.any(Function),
                onFinish: expect.any(Function),
            }),
        );
        expect(button).toBeDisabled();

        const options = getLastPostOptions();

        await act(async () => {
            options.onFinish?.();
        });

        expect(button).not.toBeDisabled();
    });

    it("shows non-field submission errors and clears them before retrying", async () => {
        render(
            <SubscriptionForm
                plan={plan}
                cards={cards}
                minimumStartDate="2026-03-08"
            />,
        );

        const { form } = getForm();

        fireEvent.submit(form);

        const firstOptions = getLastPostOptions();

        await act(async () => {
            firstOptions.onError?.({
                subscription: "サブスクリプションの登録に失敗しました。",
            });
            firstOptions.onFinish?.();
        });

        expect(
            screen.getByText("サブスクリプションの登録に失敗しました。"),
        ).toBeInTheDocument();

        fireEvent.submit(form);

        expect(
            screen.queryByText("サブスクリプションの登録に失敗しました。"),
        ).not.toBeInTheDocument();
    });
});
