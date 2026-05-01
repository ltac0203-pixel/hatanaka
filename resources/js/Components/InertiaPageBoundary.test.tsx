import { PageErrorBoundary } from "@/Components/InertiaPageBoundary";
import { render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

interface ThrowingContentProps {
    label: string;
    shouldThrow: boolean;
}

function ThrowingContent({ label, shouldThrow }: ThrowingContentProps) {
    if (shouldThrow) {
        throw new Error("render failure");
    }

    return <div>{label}</div>;
}

describe("PageErrorBoundary", () => {
    beforeEach(() => {
        vi.spyOn(console, "error").mockImplementation(() => {});
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it("recovers when the rendered page identity changes", () => {
        const { rerender } = render(
            <PageErrorBoundary
                pageComponent="Dashboard"
                pageKey={1}
                pageUrl="/dashboard"
            >
                <ThrowingContent label="Dashboard" shouldThrow />
            </PageErrorBoundary>,
        );

        expect(screen.getByText("エラーが発生しました")).toBeInTheDocument();

        rerender(
            <PageErrorBoundary
                pageComponent="Dashboard"
                pageKey={1}
                pageUrl="/dashboard"
            >
                <ThrowingContent label="Still blocked" shouldThrow={false} />
            </PageErrorBoundary>,
        );

        expect(screen.queryByText("Still blocked")).not.toBeInTheDocument();

        rerender(
            <PageErrorBoundary
                pageComponent="Plan/Index"
                pageKey={2}
                pageUrl="/plans"
            >
                <ThrowingContent label="Plans" shouldThrow={false} />
            </PageErrorBoundary>,
        );

        expect(screen.queryByText("エラーが発生しました")).not.toBeInTheDocument();
        expect(screen.getByText("Plans")).toBeInTheDocument();
    });
});
