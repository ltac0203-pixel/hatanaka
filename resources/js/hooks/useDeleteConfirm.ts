import { router } from "@inertiajs/react";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { extractRequestErrorMessage } from "@/utils/extractRequestErrorMessage";

interface DeleteConfirmConfig {
    title: string;
    message: string;
    confirmLabel: string;
    deleteUrl: string;
    fallbackErrorMessage: string;
}

export function useDeleteConfirm() {
    const { dialogProps, open, close, setError, stopProcessing } =
        useConfirmDialog();

    function confirmDelete(config: DeleteConfirmConfig) {
        open({
            title: config.title,
            message: config.message,
            confirmLabel: config.confirmLabel,
            variant: "danger",
            onConfirm: () => {
                router.delete(config.deleteUrl, {
                    preserveScroll: true,
                    onSuccess: () => close(),
                    onError: (errors) =>
                        setError(
                            extractRequestErrorMessage(
                                errors,
                                config.fallbackErrorMessage,
                            ),
                        ),
                    onFinish: () => stopProcessing(),
                });
            },
        });
    }

    return { dialogProps, confirmDelete };
}
