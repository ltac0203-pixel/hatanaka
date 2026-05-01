import Error from "@/Pages/Error";
import { render, screen } from "@testing-library/react";
import type { AnchorHTMLAttributes } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("@inertiajs/react", () => ({
    Head: ({ title }: { title: string }) => (
        <div data-testid="head-title">{title}</div>
    ),
    Link: ({
        children,
        href,
        ...props
    }: AnchorHTMLAttributes<HTMLAnchorElement> & {
        href: string;
    }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
}));

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

describe("Error page", () => {
    beforeEach(() => {
        vi.stubGlobal(
            "route",
            (name: string) => `/__mocked__/${name}`,
        );
    });

    it("renders a dedicated message for 429 errors", () => {
        render(<Error {...pageProps} status={429} />);

        expect(screen.getByText("Error 429")).toBeInTheDocument();
        expect(screen.getByText("リクエスト過多")).toBeInTheDocument();
        expect(
            screen.getByText(
                "アクセスが集中しています。しばらく待ってから再度お試しください。",
            ),
        ).toBeInTheDocument();
    });

    it("renders a dedicated message for 504 errors", () => {
        render(<Error {...pageProps} status={504} />);

        expect(screen.getByText("Error 504")).toBeInTheDocument();
        expect(screen.getByText("タイムアウト")).toBeInTheDocument();
        expect(
            screen.getByText(
                "外部サービスの応答がタイムアウトしました。しばらくしてから再度お試しください。",
            ),
        ).toBeInTheDocument();
    });
});
