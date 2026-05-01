import { describe, expect, it } from "vitest";
import { getButtonClassName, getLinkClassName } from "@/Components/actionStyles";

describe("actionStyles", () => {
    it("builds disabled button classes from shared CTA styles", () => {
        const className = getButtonClassName({
            tone: "primary",
            disabled: true,
        });

        expect(className).toContain("cursor-not-allowed");
        expect(className).toContain("opacity-25");
        expect(className).toContain("border-black");
        expect(className).not.toContain("hover:");
    });

    it("builds comfortable CTA link classes", () => {
        const className = getLinkClassName({
            variant: "cta",
            size: "comfortable",
        });

        expect(className).toContain("py-2.5");
        expect(className).toContain("text-sm");
        expect(className).toContain("tracking-wide");
        expect(className).not.toContain("hover:");
    });

    it("switches nav link classes by active state", () => {
        const inactiveClassName = getLinkClassName({
            variant: "nav",
            active: false,
        });
        const activeClassName = getLinkClassName({
            variant: "nav",
            active: true,
        });

        expect(inactiveClassName).toContain("text-gray-400");
        expect(activeClassName).toContain("bg-white");
        expect(activeClassName).toContain("text-black");
    });
});
