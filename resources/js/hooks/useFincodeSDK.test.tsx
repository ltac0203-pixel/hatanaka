import { renderHook, waitFor, act } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { useFincodeSDK } from "@/hooks/useFincodeSDK";
import { t } from "@/i18n";

const SDK_URL = "https://js.test.fincode.jp/v1/fincode.js";
const SDK_SELECTOR = 'script[data-fincode-sdk="true"]';
const LOAD_FAILED_MESSAGE = t("fincodeSdk.errorLoadFailed");

function createOptions() {
    return {
        sdkUrl: SDK_URL,
        sriHash: null,
        configError: null,
    };
}

function getSdkScripts(): HTMLScriptElement[] {
    return Array.from(
        document.head.querySelectorAll<HTMLScriptElement>(SDK_SELECTOR),
    );
}

function appendExistingScript(state?: string) {
    const script = document.createElement("script");
    script.src = SDK_URL;
    script.async = true;
    script.dataset.fincodeSdk = "true";

    if (state !== undefined) {
        script.dataset.fincodeSdkState = state;
    }

    document.head.appendChild(script);

    return script;
}

function setWindowFincode() {
    Object.defineProperty(window, "Fincode", {
        configurable: true,
        writable: true,
        value: vi.fn(),
    });
}

function clearWindowFincode() {
    Reflect.deleteProperty(window, "Fincode");
}

describe("useFincodeSDK", () => {
    beforeEach(() => {
        clearWindowFincode();
        document.head
            .querySelectorAll(SDK_SELECTOR)
            .forEach((script) => script.remove());
    });

    afterEach(() => {
        clearWindowFincode();
        vi.restoreAllMocks();
        document.head
            .querySelectorAll(SDK_SELECTOR)
            .forEach((script) => script.remove());
    });

    it("becomes ready immediately when the SDK global already exists", () => {
        setWindowFincode();

        const { result } = renderHook(() => useFincodeSDK(createOptions()));

        expect(result.current.sdkState).toEqual({
            status: "ready",
            message: null,
        });
        expect(getSdkScripts()).toHaveLength(0);
    });

    it("creates a new loading script when none exists", () => {
        const { result } = renderHook(() => useFincodeSDK(createOptions()));
        const [script] = getSdkScripts();

        expect(result.current.sdkState).toEqual({
            status: "loading",
            message: null,
        });
        expect(script).toBeDefined();
        expect(script.dataset.fincodeSdkState).toBe("loading");
    });

    it("marks the script ready after a successful load", async () => {
        renderHook(() => useFincodeSDK(createOptions()));
        const [script] = getSdkScripts();

        setWindowFincode();

        act(() => {
            script.dispatchEvent(new Event("load"));
        });

        await waitFor(() => {
            expect(script.dataset.fincodeSdkState).toBe("ready");
        });
    });

    it("reports an error when the script load event fires without the SDK global", async () => {
        const { result } = renderHook(() => useFincodeSDK(createOptions()));
        const [script] = getSdkScripts();

        act(() => {
            script.dispatchEvent(new Event("load"));
        });

        await waitFor(() => {
            expect(result.current.sdkState).toEqual({
                status: "error",
                message: LOAD_FAILED_MESSAGE,
            });
        });
        expect(script.dataset.fincodeSdkState).toBe("error");
    });

    it("reports an error when the new script fails to load", async () => {
        const { result } = renderHook(() => useFincodeSDK(createOptions()));
        const [script] = getSdkScripts();

        act(() => {
            script.dispatchEvent(new Event("error"));
        });

        await waitFor(() => {
            expect(result.current.sdkState).toEqual({
                status: "error",
                message: LOAD_FAILED_MESSAGE,
            });
        });
        expect(script.dataset.fincodeSdkState).toBe("error");
    });

    it("reuses an existing loading script instead of creating a duplicate", () => {
        const script = appendExistingScript("loading");

        renderHook(() => useFincodeSDK(createOptions()));

        expect(getSdkScripts()).toEqual([script]);
    });

    it("replaces an existing failed script before retrying", () => {
        const failedScript = appendExistingScript("error");

        renderHook(() => useFincodeSDK(createOptions()));

        const [replacementScript] = getSdkScripts();

        expect(getSdkScripts()).toHaveLength(1);
        expect(replacementScript).not.toBe(failedScript);
        expect(replacementScript.dataset.fincodeSdkState).toBe("loading");
    });

    it("replaces a legacy script without an explicit state", () => {
        const legacyScript = appendExistingScript();

        renderHook(() => useFincodeSDK(createOptions()));

        const [replacementScript] = getSdkScripts();

        expect(getSdkScripts()).toHaveLength(1);
        expect(replacementScript).not.toBe(legacyScript);
        expect(replacementScript.dataset.fincodeSdkState).toBe("loading");
    });

    it("replaces an inconsistent ready script when the SDK global is missing", () => {
        const readyScript = appendExistingScript("ready");

        renderHook(() => useFincodeSDK(createOptions()));

        const [replacementScript] = getSdkScripts();

        expect(getSdkScripts()).toHaveLength(1);
        expect(replacementScript).not.toBe(readyScript);
        expect(replacementScript.dataset.fincodeSdkState).toBe("loading");
    });

    it("does not keep retrying after the replacement script fails", async () => {
        appendExistingScript("error");
        const { result } = renderHook(() => useFincodeSDK(createOptions()));
        const [replacementScript] = getSdkScripts();

        act(() => {
            replacementScript.dispatchEvent(new Event("error"));
        });

        await waitFor(() => {
            expect(result.current.sdkState).toEqual({
                status: "error",
                message: LOAD_FAILED_MESSAGE,
            });
        });
        expect(getSdkScripts()).toEqual([replacementScript]);
    });

    it("removes event listeners from an existing loading script on unmount", () => {
        const script = appendExistingScript("loading");
        const removeEventListenerSpy = vi.spyOn(script, "removeEventListener");

        const { unmount } = renderHook(() => useFincodeSDK(createOptions()));

        unmount();

        expect(removeEventListenerSpy).toHaveBeenCalledWith(
            "load",
            expect.any(Function),
        );
        expect(removeEventListenerSpy).toHaveBeenCalledWith(
            "error",
            expect.any(Function),
        );
    });
});
