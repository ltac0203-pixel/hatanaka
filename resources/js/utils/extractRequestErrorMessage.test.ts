import { extractRequestErrorMessage } from "@/utils/extractRequestErrorMessage";
import { describe, expect, it } from "vitest";

const FALLBACK = "fallback message";

describe("extractRequestErrorMessage", () => {
    it("returns the first non-empty string value", () => {
        expect(
            extractRequestErrorMessage(
                {
                    subscription: "サブスクリプションの解約に失敗しました。",
                },
                FALLBACK,
            ),
        ).toBe("サブスクリプションの解約に失敗しました。");
    });

    it("skips empty / whitespace-only strings and returns the next non-empty string", () => {
        expect(
            extractRequestErrorMessage(
                {
                    first: "",
                    second: "   ",
                    third: "実際のエラー",
                },
                FALLBACK,
            ),
        ).toBe("実際のエラー");
    });

    it("returns the first non-empty string from an array value", () => {
        expect(
            extractRequestErrorMessage(
                {
                    card: ["", "   ", "アクティブな契約で使用中のカードは削除できません。"],
                },
                FALLBACK,
            ),
        ).toBe("アクティブな契約で使用中のカードは削除できません。");
    });

    it("returns the fallback when all values are empty / undefined / empty arrays", () => {
        expect(
            extractRequestErrorMessage(
                {
                    a: undefined,
                    b: "",
                    c: "   ",
                    d: [],
                    e: ["", "   "],
                },
                FALLBACK,
            ),
        ).toBe(FALLBACK);
    });

    it("returns the fallback when the errors object is empty", () => {
        expect(extractRequestErrorMessage({}, FALLBACK)).toBe(FALLBACK);
    });
});
