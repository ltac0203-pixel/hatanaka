import { useEffect, useReducer } from "react";
import { t } from "@/i18n";

const ALLOWED_SDK_ORIGINS = [
    "https://js.fincode.jp",
    "https://js.test.fincode.jp",
] as const;
const SDK_SCRIPT_SELECTOR = 'script[data-fincode-sdk="true"]';
const SDK_LOAD_FAILED_MESSAGE = t("fincodeSdk.errorLoadFailed");

type FincodeSdkScriptState = "loading" | "ready" | "error";

function isAllowedSdkUrl(url: string): boolean {
    try {
        const parsed = new URL(url);
        return (
            parsed.protocol === "https:" &&
            ALLOWED_SDK_ORIGINS.some((origin) => parsed.origin === origin)
        );
    } catch {
        return false;
    }
}

function isFincodeSdkScriptState(
    value: string | undefined,
): value is FincodeSdkScriptState {
    return value === "loading" || value === "ready" || value === "error";
}

function getSdkScriptState(
    script: HTMLScriptElement,
): FincodeSdkScriptState | null {
    const state = script.dataset.fincodeSdkState;
    return isFincodeSdkScriptState(state) ? state : null;
}

function markSdkScriptState(
    script: HTMLScriptElement,
    state: FincodeSdkScriptState,
) {
    script.dataset.fincodeSdk = "true";
    script.dataset.fincodeSdkState = state;
}

function findExistingSdkScript(sdkUrl: string): HTMLScriptElement | null {
    const scripts =
        document.querySelectorAll<HTMLScriptElement>(SDK_SCRIPT_SELECTOR);

    for (const script of scripts) {
        if (script.getAttribute("src") === sdkUrl) {
            return script;
        }
    }

    return null;
}

function createSdkScript(
    sdkUrl: string,
    sriHash: string | null,
): HTMLScriptElement {
    const script = document.createElement("script");
    script.src = sdkUrl;
    script.async = true;
    markSdkScriptState(script, "loading");

    if (sriHash) {
        script.integrity = sriHash;
        script.crossOrigin = "anonymous";
    }

    return script;
}

function attachSdkListeners(
    script: HTMLScriptElement,
    onReady: () => void,
    onError: () => void,
) {
    const handleLoad = () => {
        if (typeof window.Fincode === "function") {
            markSdkScriptState(script, "ready");
            onReady();
            return;
        }

        markSdkScriptState(script, "error");
        onError();
    };
    const handleError = () => {
        markSdkScriptState(script, "error");
        onError();
    };

    script.addEventListener("load", handleLoad);
    script.addEventListener("error", handleError);

    return () => {
        script.removeEventListener("load", handleLoad);
        script.removeEventListener("error", handleError);
    };
}

interface UseFincodeSDKOptions {
    sdkUrl: string | null;
    sriHash: string | null;
    configError: string | null;
}

export interface FincodeSdkState {
    status: "blocked" | "loading" | "ready" | "error";
    message: string | null;
}

interface UseFincodeSDKResult {
    sdkState: FincodeSdkState;
    setSubmissionError: (error: string) => void;
    clearSubmissionError: () => void;
}

type FincodeSdkAction =
    | {
          type: "block";
          message: string;
      }
    | {
          type: "start-loading";
      }
    | {
          type: "ready";
      }
    | {
          type: "load-failed";
          message: string;
      }
    | {
          type: "submission-failed";
          message: string;
      }
    | {
          type: "clear-submission-error";
      };

function createInitialSdkState({
    sdkUrl,
    configError,
}: Pick<UseFincodeSDKOptions, "sdkUrl" | "configError">): FincodeSdkState {
    if (configError || !sdkUrl) {
        return {
            status: "blocked",
            message: configError ?? SDK_LOAD_FAILED_MESSAGE,
        };
    }

    if (!isAllowedSdkUrl(sdkUrl)) {
        return {
            status: "error",
            message: t("fincodeSdk.errorInvalidDomain"),
        };
    }

    if (typeof window !== "undefined" && typeof window.Fincode === "function") {
        return {
            status: "ready",
            message: null,
        };
    }

    return {
        status: "loading",
        message: null,
    };
}

function fincodeSdkReducer(
    state: FincodeSdkState,
    action: FincodeSdkAction,
): FincodeSdkState {
    switch (action.type) {
        case "block":
            return {
                status: "blocked",
                message: action.message,
            };
        case "start-loading":
            return {
                status: "loading",
                message: null,
            };
        case "ready":
            return {
                status: "ready",
                message: null,
            };
        case "load-failed":
            return {
                status: "error",
                message: action.message,
            };
        case "submission-failed":
            if (state.status !== "ready") {
                return state;
            }

            return {
                status: "ready",
                message: action.message,
            };
        case "clear-submission-error":
            if (state.status !== "ready" || state.message === null) {
                return state;
            }

            return {
                status: "ready",
                message: null,
            };
    }
}

export function useFincodeSDK(
    options: UseFincodeSDKOptions,
): UseFincodeSDKResult {
    const { sdkUrl, sriHash, configError } = options;
    const [sdkState, dispatch] = useReducer(
        fincodeSdkReducer,
        {
            sdkUrl,
            configError,
        },
        createInitialSdkState,
    );

    function setSubmissionError(message: string) {
        if (message.trim() === "") {
            dispatch({
                type: "clear-submission-error",
            });
            return;
        }

        dispatch({
            type: "submission-failed",
            message,
        });
    }

    function clearSubmissionError() {
        dispatch({
            type: "clear-submission-error",
        });
    }

    useEffect(() => {
        if (configError || !sdkUrl) {
            dispatch({
                type: "block",
                message: configError ?? SDK_LOAD_FAILED_MESSAGE,
            });
            return;
        }

        if (!isAllowedSdkUrl(sdkUrl)) {
            dispatch({
                type: "load-failed",
                message: t("fincodeSdk.errorInvalidDomain"),
            });
            return;
        }

        if (typeof window.Fincode === "function") {
            dispatch({
                type: "ready",
            });
            return;
        }

        dispatch({
            type: "start-loading",
        });

        const onReady = () => {
            dispatch({
                type: "ready",
            });
        };
        const onError = () => {
            dispatch({
                type: "load-failed",
                message: SDK_LOAD_FAILED_MESSAGE,
            });
        };
        const existingScript = findExistingSdkScript(sdkUrl);

        if (existingScript) {
            const existingScriptState = getSdkScriptState(existingScript);

            if (existingScriptState === "loading") {
                return attachSdkListeners(existingScript, onReady, onError);
            }

            existingScript.remove();
        }

        const script = createSdkScript(sdkUrl, sriHash);
        const detachListeners = attachSdkListeners(script, onReady, onError);
        document.head.appendChild(script);

        return () => {
            detachListeners();
        };
    }, [configError, sdkUrl, sriHash]);

    return { sdkState, setSubmissionError, clearSubmissionError };
}
