import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Checkbox from "@/Components/Checkbox";
import InputError from "@/Components/InputError";
import FincodePaymentForm, {
    FincodePaymentFormRef,
} from "@/Components/FincodePaymentForm";
import PrimaryButton from "@/Components/PrimaryButton";
import { useFincodeSDK } from "@/hooks/useFincodeSDK";
import { Head, router, usePage } from "@inertiajs/react";
import { FormEventHandler, useRef, useState } from "react";
import { PageProps } from "@/types";
import { t } from "@/i18n";
import { appRoutes } from "@/utils/routes";

interface Props extends PageProps {
    fincode_public_key: string;
    fincode_sdk_url: string | null;
    fincode_sdk_sri_hash: string | null;
    fincode_config_error: string | null;
}

export default function Create({
    fincode_public_key,
    fincode_sdk_url,
    fincode_sdk_sri_hash,
    fincode_config_error,
}: Props) {
    const { errors } = usePage().props;
    const formRef = useRef<FincodePaymentFormRef>(null);

    const { sdkState, setSubmissionError, clearSubmissionError } =
        useFincodeSDK({
            sdkUrl: fincode_sdk_url,
            sriHash: fincode_sdk_sri_hash,
            configError: fincode_config_error,
        });

    const [isDefault, setIsDefault] = useState(false);
    const [processing, setProcessing] = useState(false);
    const isSdkReady = sdkState.status === "ready";

    const submit: FormEventHandler = async (e) => {
        e.preventDefault();

        if (!isSdkReady) {
            return;
        }

        setProcessing(true);
        clearSubmissionError();

        try {
            if (!formRef.current) {
                throw new Error(t("fincodePayment.errorFormNotInit"));
            }

            const token = await formRef.current.submit();

            router.post(
                appRoutes.cards.store(),
                {
                    token,
                    is_default: isDefault,
                },
                {
                    onError: () =>
                        setSubmissionError(t("card.tokenizeError")),
                    onFinish: () => setProcessing(false),
                },
            );
        } catch (error) {
            const errorMessage =
                error instanceof Error && error.message.trim() !== ""
                    ? error.message
                    : t("card.tokenizeError");
            setSubmissionError(errorMessage);
            console.error("Tokenization error:", error);
            setProcessing(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="app-page-title">{t("card.registerTitle")}</h2>
            }
        >
            <Head title={t("card.registerTitle")} />

            <div className="mx-auto max-w-2xl">
                <div className="app-panel-padded">
                    <form onSubmit={submit} className="space-y-6">
                        {sdkState.status === "blocked" ||
                        sdkState.status === "error" ? (
                            <div className="border-2 border-red-500 bg-red-50 p-4">
                                <p className="text-sm text-red-800">
                                    {sdkState.message}
                                </p>
                            </div>
                        ) : (
                            <>
                                {sdkState.status === "loading" && (
                                    <div className="border-2 border-gray-300 bg-gray-50 p-4">
                                        <p className="text-sm text-gray-700">
                                            {t("card.loading")}
                                        </p>
                                    </div>
                                )}
                                {isSdkReady && (
                                    <FincodePaymentForm
                                        ref={formRef}
                                        publicKey={fincode_public_key}
                                        onError={setSubmissionError}
                                    />
                                )}
                            </>
                        )}

                        <InputError
                            message={(errors as Record<string, string>).token}
                            className="mt-2"
                        />

                        <div className="flex items-center">
                            <Checkbox
                                id="is_default"
                                checked={isDefault}
                                onChange={(e) => setIsDefault(e.target.checked)}
                            />
                            <label
                                htmlFor="is_default"
                                className="ml-2 text-sm text-gray-600"
                            >
                                {t("card.setAsDefault")}
                            </label>
                        </div>

                        {isSdkReady && sdkState.message && (
                            <div className="border-2 border-red-500 bg-red-50 p-4">
                                <p className="text-sm text-red-800">
                                    {sdkState.message}
                                </p>
                            </div>
                        )}

                        <div className="flex items-center justify-end">
                            <PrimaryButton
                                className="ml-4"
                                disabled={processing || !isSdkReady}
                            >
                                {processing
                                    ? t("card.processingButton")
                                    : sdkState.status === "loading"
                                      ? t("card.loadingButton")
                                      : t("card.submitButton")}
                            </PrimaryButton>
                        </div>

                        <p className="text-right text-sm text-gray-700">
                            {t("card.securityNote")}
                        </p>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
