import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import ActionLink from "@/Components/ActionLink";
import PlanSummary from "@/Components/PlanSummary";
import SubscriptionForm from "@/Pages/Plan/Partials/SubscriptionForm";
import { Head } from "@inertiajs/react";
import { FincodeCard, Plan } from "@/types/subscription";
import { PageProps } from "@/types";
import { t } from "@/i18n";
import { appRoutes } from "@/utils/routes";

interface Props extends PageProps {
    plan: Plan;
    cards: FincodeCard[];
    hasActiveSubscription: boolean;
    minimumStartDate: string;
}

export default function Show({
    plan,
    cards,
    hasActiveSubscription,
    minimumStartDate,
}: Props) {
    return (
        <AuthenticatedLayout
            header={<h2 className="app-page-title">{t("plan.detailTitle")}</h2>}
        >
            <Head title={`${t("plan.detailTitle")} - ${plan.name}`} />

            <div className="mx-auto max-w-4xl space-y-6">
                {/* 契約前に内容と価格を再確認できるようプラン情報を先に見せる。 */}
                <div className="app-panel-padded">
                    <PlanSummary
                        plan={plan}
                        descriptionClassName="mt-4 text-gray-600"
                        featureHeading={
                            <h4 className="font-semibold uppercase tracking-wide text-black">
                                {t("plan.features")}
                            </h4>
                        }
                        featureSectionClassName="mt-6"
                        featureListClassName="mt-2 space-y-2"
                    />
                </div>

                {/* 条件を満たす利用者だけがその場で契約へ進めるよう入力欄を続ける。 */}
                <div className="app-panel-padded">
                    <h3 className="text-lg font-semibold uppercase tracking-wide text-black">
                        {t("plan.subscriptionSection")}
                    </h3>

                    {hasActiveSubscription ? (
                        <div className="mt-4 border-2 border-gray-300 bg-gray-50 p-4">
                            <p className="text-sm font-medium text-gray-700">
                                {t("plan.hasActiveSubscription")}
                            </p>
                            <ActionLink
                                href={appRoutes.subscription.index()}
                                variant="underlined"
                                className="mt-2 font-medium text-gray-700"
                            >
                                {t("plan.goToSubscription")}
                            </ActionLink>
                        </div>
                    ) : cards.length > 0 ? (
                        <SubscriptionForm
                            plan={plan}
                            cards={cards}
                            minimumStartDate={minimumStartDate}
                        />
                    ) : (
                        <div className="mt-4">
                            <p className="text-gray-500">{t("plan.noCard")}</p>
                            <ActionLink
                                href={appRoutes.cards.create()}
                                variant="cta"
                                className="mt-4"
                            >
                                {t("plan.registerCard")}
                            </ActionLink>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
