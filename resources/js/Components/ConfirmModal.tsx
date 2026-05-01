import Modal from "@/Components/Modal";
import DangerButton from "@/Components/DangerButton";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";
import { t } from "@/i18n";

interface ConfirmModalProps {
    show: boolean;
    title: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: "danger" | "default";
    errorMessage?: string | null;
    processing?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
}

export default function ConfirmModal({
    show,
    title,
    message,
    confirmLabel = t("common.execute"),
    cancelLabel = t("common.cancel"),
    variant = "default",
    errorMessage = null,
    processing = false,
    onConfirm,
    onCancel,
}: ConfirmModalProps) {
    const ActionButton = variant === "danger" ? DangerButton : PrimaryButton;

    return (
        <Modal show={show} maxWidth="md" onClose={onCancel}>
            <div className="p-6">
                <h2 className="text-lg font-semibold uppercase tracking-wide text-black">
                    {title}
                </h2>
                <p className="mt-2 text-sm text-gray-600">{message}</p>
                {errorMessage && (
                    <div
                        className="mt-4 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                        role="alert"
                    >
                        {errorMessage}
                    </div>
                )}
                <div className="mt-6 flex justify-end space-x-3">
                    <SecondaryButton onClick={onCancel} disabled={processing}>
                        {cancelLabel}
                    </SecondaryButton>
                    <ActionButton onClick={onConfirm} disabled={processing}>
                        {confirmLabel}
                    </ActionButton>
                </div>
            </div>
        </Modal>
    );
}
