import { useState } from "react";

interface ConfirmDialogConfig {
    title: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: "danger" | "default";
    onConfirm: () => void;
}

interface ConfirmDialogProps {
    show: boolean;
    title: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: "danger" | "default";
    errorMessage: string | null;
    processing: boolean;
    onConfirm: () => void;
    onCancel: () => void;
}

export function useConfirmDialog() {
    const [config, setConfig] = useState<ConfirmDialogConfig | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    function open(newConfig: ConfirmDialogConfig) {
        setConfig(newConfig);
        setErrorMessage(null);
        setProcessing(false);
    }

    function close() {
        setConfig(null);
        setErrorMessage(null);
        setProcessing(false);
    }

    function setError(message: string | null) {
        setErrorMessage(message);
    }

    function stopProcessing() {
        setProcessing(false);
    }

    function handleConfirm() {
        if (!config) return;
        setErrorMessage(null);
        setProcessing(true);
        config.onConfirm();
    }

    const dialogProps: ConfirmDialogProps = {
        show: config !== null,
        title: config?.title ?? "",
        message: config?.message ?? "",
        confirmLabel: config?.confirmLabel,
        cancelLabel: config?.cancelLabel,
        variant: config?.variant,
        errorMessage,
        processing,
        onConfirm: handleConfirm,
        onCancel: close,
    };

    return { dialogProps, open, close, setError, stopProcessing };
}
