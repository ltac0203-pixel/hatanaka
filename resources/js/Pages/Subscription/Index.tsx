import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import ActionLink from "@/Components/ActionLink";
import ConfirmModal from "@/Components/ConfirmModal";
import DangerButton from "@/Components/DangerButton";
import TextMark from "@/Components/TextMark";
import { Head, router } from "@inertiajs/react";
import { FincodeCard, Subscription } from "@/types/subscription";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { PageProps } from "@/types";
import { t } from "@/i18n";
import { appRoutes } from "@/utils/routes";

interface Props extends PageProps {
    subscription: Subscription | null;
    cards: FincodeCard[];
}

type RequestErrors = Record<string, string | string[] | undefined>;

const SUBSCRIPTION_CANCEL_ERROR =
    "サブスクリプションの解約に失敗しました。時間をおいて再試行してください。";
const CARD_DELETE_ERROR =
    "カードの削除に失敗しました。時間をおいて再試行してください。";

function extractRequestErrorMessage(
    errors: RequestErrors,
    fallbackMessage: string,
) {
    for (const value of Object.values(errors)) {
        if (typeof value === "string" && value.trim() !== "") {
            return value;
        }

        if (Array.isArray(value)) {
            const message = value.find(
                (item): item is string =>
                    typeof item === "string" && item.trim() !== "",
            );

            if (message) {
                return message;
            }
        }
    }

    return fallbackMessage;
}

const statusBadge = (status: string) => {
    const styles: Record<string, string> = {
        active: "bg-black text-white border-black",
        canceled: "bg-gray-50 text-gray-600 border-gray-200",
        expired: "bg-gray-100 text-gray-500 border-gray-300",
        unpaid: "bg-red-50 text-red-700 border-red-200",
        incomplete: "bg-gray-100 text-gray-500 border-gray-300",
    };
    const labels: Record<string, string> = {
        active: t("subscription.statusLabels.active"),
        canceled: t("subscription.statusLabels.canceled"),
        expired: t("subscription.statusLabels.expired"),
        unpaid: t("subscription.statusLabels.unpaid"),
        incomplete: t("subscription.statusLabels.incomplete"),
    };
    return (
        <span
            className={`inline-flex items-center border px-3 py-1 text-xs font-semibold uppercase tracking-wide ${styles[status] || ""}`}
        >
            {labels[status] || status}
        </span>
    );
};

export default function Index({ subscription, cards }: Props) {
    const { dialogProps, open, close, setError, stopProcessing } =
        useConfirmDialog();

    return (
        <AuthenticatedLayout
            header={
                <h2 className="app-page-title">{t("subscription.title")}</h2>
            }
        >
            <Head title={t("subscription.title")} />

            <div className="space-y-6">
                {/* 契約状況を最初に見せ、次に必要な操作を判断しやすくする。 */}
                <div className="app-panel-padded">
                    <h3 className="text-lg font-semibold uppercase tracking-wide text-black">
                        {t("subscription.currentSection")}
                    </h3>
                    {subscription ? (
                        <div className="mt-4 space-y-4">
                            <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                                <div>
                                    <p className="text-sm text-gray-500 uppercase tracking-wide">
                                        {t("subscription.plan")}
                                    </p>
                                    <p className="mt-1 text-lg font-semibold text-black">
                                        {subscription.plan?.name || "-"}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500 uppercase tracking-wide">
                                        {t("subscription.price")}
                                    </p>
                                    <p className="mt-1 text-lg font-semibold text-black">
                                        {subscription.plan?.price_display ||
                                            "-"}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500 uppercase tracking-wide">
                                        {t("subscription.status")}
                                    </p>
                                    <div className="mt-1">
                                        {statusBadge(subscription.status)}
                                    </div>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500 uppercase tracking-wide">
                                        {t("subscription.nextChargeDate")}
                                    </p>
                                    <p className="mt-1 text-lg font-semibold text-black">
                                        {subscription.next_charge_date || "-"}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500 uppercase tracking-wide">
                                        {t("subscription.paymentCard")}
                                    </p>
                                    <p className="mt-1 text-lg font-semibold text-black">
                                        {subscription.card?.display_name || "-"}
                                    </p>
                                </div>
                            </div>
                            {subscription.status === "active" && (
                                <div className="pt-4 border-t border-gray-200">
                                    <DangerButton
                                        onClick={() =>
                                            open({
                                                title: t(
                                                    "subscription.cancelDialog.title",
                                                ),
                                                message: t(
                                                    "subscription.cancelDialog.message",
                                                ),
                                                confirmLabel: t(
                                                    "subscription.cancelDialog.confirmLabel",
                                                ),
                                                variant: "danger",
                                                onConfirm: () => {
                                                    router.delete(
                                                        appRoutes.subscription.destroy(
                                                            subscription.id,
                                                        ),
                                                        {
                                                            preserveScroll: true,
                                                            onSuccess: () =>
                                                                close(),
                                                            onError: (errors) =>
                                                                setError(
                                                                    extractRequestErrorMessage(
                                                                        errors,
                                                                        SUBSCRIPTION_CANCEL_ERROR,
                                                                    ),
                                                                ),
                                                            onFinish: () =>
                                                                stopProcessing(),
                                                        },
                                                    );
                                                },
                                            })
                                        }
                                    >
                                        {t("subscription.cancelButton")}
                                    </DangerButton>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="mt-4">
                            <p className="text-gray-500">
                                {t("subscription.noSubscription")}
                            </p>
                            <ActionLink
                                href={appRoutes.plans.index()}
                                variant="cta"
                                className="mt-4"
                            >
                                {t("subscription.selectPlan")}
                            </ActionLink>
                        </div>
                    )}
                </div>

                {/* 決済手段を同じ画面で管理できるよう契約情報の直後に並べる。 */}
                <div className="app-panel-padded">
                    <div className="flex items-center justify-between">
                        <h3 className="text-lg font-semibold uppercase tracking-wide text-black">
                            {t("subscription.cardsSection")}
                        </h3>
                        <ActionLink
                            href={appRoutes.cards.create()}
                            variant="cta"
                        >
                            {t("subscription.addCard")}
                        </ActionLink>
                    </div>
                    <div className="mt-4 space-y-3">
                        {cards.length > 0 ? (
                            cards.map((card) => (
                                <div
                                    key={card.id}
                                    className="flex items-center justify-between border-2 border-gray-200 p-4"
                                >
                                    <div className="flex items-center space-x-4">
                                        <TextMark
                                            label="C"
                                            boxed
                                            className="h-10 w-10 text-sm text-black"
                                        />
                                        <div>
                                            <p className="font-semibold text-black">
                                                {card.display_name}
                                            </p>
                                            <p className="text-sm text-gray-500">
                                                {t("subscription.cardExpiry")}{" "}
                                                {card.expiry_display}
                                            </p>
                                        </div>
                                        {card.is_default && (
                                            <span className="border-2 border-black bg-black px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-white">
                                                {t("subscription.cardDefault")}
                                            </span>
                                        )}
                                    </div>
                                    <DangerButton
                                        onClick={() =>
                                            open({
                                                title: t(
                                                    "subscription.deleteCardDialog.title",
                                                ),
                                                message: t(
                                                    "subscription.deleteCardDialog.message",
                                                ),
                                                confirmLabel: t(
                                                    "subscription.deleteCardDialog.confirmLabel",
                                                ),
                                                variant: "danger",
                                                onConfirm: () => {
                                                    router.delete(
                                                        appRoutes.cards.destroy(
                                                            card.id,
                                                        ),
                                                        {
                                                            preserveScroll: true,
                                                            onSuccess: () =>
                                                                close(),
                                                            onError: (errors) =>
                                                                setError(
                                                                    extractRequestErrorMessage(
                                                                        errors,
                                                                        CARD_DELETE_ERROR,
                                                                    ),
                                                                ),
                                                            onFinish: () =>
                                                                stopProcessing(),
                                                        },
                                                    );
                                                },
                                            })
                                        }
                                    >
                                        {t("common.delete")}
                                    </DangerButton>
                                </div>
                            ))
                        ) : (
                            <p className="text-gray-500">
                                {t("subscription.noCards")}
                            </p>
                        )}
                    </div>
                </div>
            </div>

            <ConfirmModal {...dialogProps} />
        </AuthenticatedLayout>
    );
}
