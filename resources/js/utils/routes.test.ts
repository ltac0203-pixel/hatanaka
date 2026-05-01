import { appRoutes } from "@/utils/routes";
import { beforeEach, describe, expect, it, vi } from "vitest";

describe("appRoutes", () => {
    const routeMock = vi.fn(
        (name: string, params?: Record<string, string | number>) =>
            JSON.stringify({ name, params: params ?? null }),
    );

    beforeEach(() => {
        routeMock.mockClear();
        vi.stubGlobal("route", routeMock);
    });

    it("builds static page routes from named routes", () => {
        expect(appRoutes.home()).toBe(
            JSON.stringify({ name: "home", params: null }),
        );
        expect(appRoutes.plans.index()).toBe(
            JSON.stringify({ name: "plans.index", params: null }),
        );
        expect(appRoutes.subscription.index()).toBe(
            JSON.stringify({ name: "subscription.index", params: null }),
        );
        expect(appRoutes.cards.create()).toBe(
            JSON.stringify({ name: "cards.create", params: null }),
        );
    });

    it("builds parameterized routes with the expected parameter names", () => {
        expect(appRoutes.plans.show("pl_test")).toBe(
            JSON.stringify({
                name: "plans.show",
                params: { fincode_plan_id: "pl_test" },
            }),
        );
        expect(appRoutes.subscription.destroy(1)).toBe(
            JSON.stringify({
                name: "subscription.destroy",
                params: { subscription: 1 },
            }),
        );
        expect(appRoutes.cards.destroy(10)).toBe(
            JSON.stringify({
                name: "cards.destroy",
                params: { card: 10 },
            }),
        );
    });
});
