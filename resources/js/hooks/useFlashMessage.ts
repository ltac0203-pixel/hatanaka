import { useEffect, useState } from "react";
import { usePage } from "@inertiajs/react";
import { PageProps } from "@/types";

export interface FlashToastProps {
    message: string | null;
    type: "success" | "error" | null;
    visible: boolean;
    onDismiss: () => void;
}

export function useFlashMessage(duration = 4000): FlashToastProps {
    const { flash } = usePage<PageProps>().props;
    const [dismissedFlashKey, setDismissedFlashKey] = useState<string | null>(
        null,
    );
    const message = flash.success ?? flash.error ?? null;
    const type = flash.success ? "success" : flash.error ? "error" : null;
    const currentFlashKey = flash.key;
    const visible = Boolean(
        message &&
        type &&
        currentFlashKey &&
        currentFlashKey !== dismissedFlashKey,
    );

    function dismiss() {
        if (!currentFlashKey) {
            return;
        }

        setDismissedFlashKey(currentFlashKey);
    }

    useEffect(() => {
        if (!visible || !currentFlashKey) {
            return;
        }

        const timer = window.setTimeout(
            () => setDismissedFlashKey(currentFlashKey),
            duration,
        );

        return () => clearTimeout(timer);
    }, [visible, currentFlashKey, duration]);

    return { message, type, visible, onDismiss: dismiss };
}
