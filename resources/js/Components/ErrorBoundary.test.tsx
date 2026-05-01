import ErrorBoundary from "@/Components/ErrorBoundary";
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

describe("ErrorBoundary", () => {
    beforeEach(() => {
        vi.spyOn(console, "error").mockImplementation(() => {});
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it("keeps the fallback rendered until resetKeys change", () => {
        const { rerender } = render(
            <ErrorBoundary resetKeys={["dashboard"]}>
                <ThrowingContent label="Dashboard" shouldThrow />
            </ErrorBoundary>,
        );

        expect(screen.getByText("エラーが発生しました")).toBeInTheDocument();

        rerender(
            <ErrorBoundary resetKeys={["dashboard"]}>
                <ThrowingContent label="Recovered" shouldThrow={false} />
            </ErrorBoundary>,
        );

        expect(screen.getByText("エラーが発生しました")).toBeInTheDocument();
        expect(screen.queryByText("Recovered")).not.toBeInTheDocument();
    });

    it("recovers after resetKeys change", () => {
        const { rerender } = render(
            <ErrorBoundary resetKeys={["dashboard"]}>
                <ThrowingContent label="Dashboard" shouldThrow />
            </ErrorBoundary>,
        );

        expect(screen.getByText("エラーが発生しました")).toBeInTheDocument();

        rerender(
            <ErrorBoundary resetKeys={["plans"]}>
                <ThrowingContent label="Recovered" shouldThrow={false} />
            </ErrorBoundary>,
        );

        expect(screen.queryByText("エラーが発生しました")).not.toBeInTheDocument();
        expect(screen.getByText("Recovered")).toBeInTheDocument();
    });
});
