import {
    forwardRef,
    useEffect,
    useId,
    useImperativeHandle,
    useRef,
} from "react";
import {
    isValidPublicKey,
    normalizeTokenPayload,
    extractApiErrorMessage,
    extractToken,
} from "@/utils/fincode";
import { t } from "@/i18n";

interface FincodePaymentFormProps {
    publicKey: string;
    onError?: (message: string) => void;
    width?: string;
    height?: string;
    className?: string;
}

export interface FincodePaymentFormRef {
    submit: () => Promise<string>;
}

function buildAppearance(): FincodeUIAppearance {
    return {
        layout: "vertical",
        hideLabel: false,
        hideHolderName: true,
        hidePayTimes: true,
        labelCardNo: t("fincodePayment.labelCardNo"),
        labelExpire: t("fincodePayment.labelExpire"),
        labelCvc: t("fincodePayment.labelCvc"),
        cardNo: "1234 5678 9012 3456",
        expireMonth: "12",
        expireYear: "31",
        cvc: "123",
        colorBackground: "ffffff",
        colorBackgroundInput: "ffffff",
        colorText: "0f0f0f",
        colorPlaceHolder: "9ca3af",
        colorLabelText: "6e6e6e",
        colorBorder: "d1d5db",
        colorError: "c12424",
        colorCheck: "000054",
    };
}

export default forwardRef<FincodePaymentFormRef, FincodePaymentFormProps>(
    function FincodePaymentForm(
        { publicKey, onError, width, height = "420", className = "" },
        ref,
    ) {
        const reactId = useId();
        const fincodeRef = useRef<FincodeInstance | null>(null);
        const uiRef = useRef<FincodeUI | null>(null);
        const onErrorRef = useRef(onError);
        const mountWidth = width ?? height;
        const containerId = `fincode-payment-container-${reactId.replace(/:/g, "-")}`;

        useEffect(() => {
            onErrorRef.current = onError;
        }, [onError]);

        useEffect(() => {
            const normalizedPublicKey = publicKey.trim();
            const reportError = (message: string) => {
                onErrorRef.current?.(message);
            };

            if (normalizedPublicKey === "") {
                reportError(t("fincodePayment.errorPublicKeyEmpty"));
                return;
            }

            if (!isValidPublicKey(normalizedPublicKey)) {
                reportError(t("fincodePayment.errorPublicKeyInvalid"));
                return;
            }

            if (typeof window.Fincode !== "function") {
                reportError(t("fincodePayment.errorSdkNotLoaded"));
                return;
            }

            try {
                const fincode = window.Fincode(normalizedPublicKey);
                const appearance = buildAppearance();
                const ui = fincode.ui(appearance);
                ui.create("payments", appearance);
                ui.mount(containerId, mountWidth);

                fincodeRef.current = fincode;
                uiRef.current = ui;
            } catch {
                console.error("Fincode UI initialization error");
                reportError(t("fincodePayment.errorInitFailed"));
            }

            return () => {
                try {
                    uiRef.current?.destroy();
                } catch {
                    // 破棄処理の失敗で画面遷移全体を止めないよう後始末の例外は握りつぶす。
                }
                uiRef.current = null;
                fincodeRef.current = null;
            };
        }, [publicKey, mountWidth, containerId]);

        useImperativeHandle(ref, () => ({
            submit: async () => {
                const fincode = fincodeRef.current;
                const ui = uiRef.current;

                if (!fincode || !ui) {
                    throw new Error(t("fincodePayment.errorFormNotInit"));
                }

                const formData = await ui.getFormData();
                if (!formData || Object.keys(formData).length === 0) {
                    throw new Error(t("fincodePayment.errorFormDataFailed"));
                }

                const payload = normalizeTokenPayload(formData);
                if (!payload) {
                    throw new Error(t("fincodePayment.errorPayloadFailed"));
                }

                const response = await new Promise<FincodeTokenResponse>(
                    (resolve, reject) => {
                        fincode.tokens(
                            payload,
                            (status, body) => {
                                if (status >= 400) {
                                    reject(
                                        new Error(
                                            extractApiErrorMessage(body) ??
                                                t(
                                                    "fincodePayment.errorTokenFailed",
                                                ),
                                        ),
                                    );
                                    return;
                                }

                                resolve(body);
                            },
                            (errorMessage) => reject(new Error(errorMessage)),
                        );
                    },
                );

                const apiErrorMessage = extractApiErrorMessage(response);
                if (apiErrorMessage) {
                    throw new Error(apiErrorMessage);
                }

                const token = extractToken(response);
                if (!token) {
                    throw new Error(t("fincodePayment.errorTokenFailed"));
                }

                return token;
            },
        }));

        return (
            <div className={className}>
                <div id={`${containerId}-form`} />
                <div id={containerId} />
            </div>
        );
    },
);
