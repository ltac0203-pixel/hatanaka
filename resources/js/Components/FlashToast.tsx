import TextMark from "@/Components/TextMark";
import { FlashToastProps } from "@/hooks/useFlashMessage";

export default function FlashToast({
    message,
    type,
    visible,
    onDismiss,
}: FlashToastProps) {
    if (!visible || !message) return null;

    const isSuccess = type === "success";

    return (
        <div
            role="alert"
            aria-live="polite"
            className={`fixed bottom-6 right-6 z-50 flex max-w-sm items-start gap-3 border-2 px-4 py-3 ${
                isSuccess
                    ? "border-black bg-black text-white"
                    : "border-red-500 bg-red-50 text-red-700"
            }`}
        >
            <TextMark
                label={isSuccess ? "OK" : "!"}
                className="mt-0.5 min-w-5 text-[10px]"
            />
            <p className="flex-1 text-sm font-medium">{message}</p>
            <button
                type="button"
                onClick={onDismiss}
                aria-label="閉じる"
                className="shrink-0 cursor-pointer"
            >
                <TextMark label="x" className="h-4 w-4 text-sm" />
            </button>
        </div>
    );
}
