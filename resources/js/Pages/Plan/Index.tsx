import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import ActionLink from "@/Components/ActionLink";
import PlanSummary from "@/Components/PlanSummary";
import { Head } from "@inertiajs/react";
import { Plan } from "@/types/subscription";
import { PageProps } from "@/types";
import { t } from "@/i18n";
import { appRoutes } from "@/utils/routes";

interface Props extends PageProps {
    plans: Plan[];
}

export default function Index({ plans }: Props) {
    return (
        <AuthenticatedLayout
            header={<h2 className="app-page-title">{t("plan.listTitle")}</h2>}
        >
            <Head title={t("plan.listTitle")} />

            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                {plans.map((plan) => (
                    <div
                        key={plan.fincode_plan_id}
                        className="app-panel flex flex-col p-6"
                    >
                        <PlanSummary
                            plan={plan}
                            className="flex flex-1 flex-col"
                            featureSectionClassName="mt-4 flex-1"
                            featureListClassName="space-y-2"
                        />
                        <div className="mt-6">
                            <ActionLink
                                href={appRoutes.plans.show(
                                    plan.fincode_plan_id,
                                )}
                                variant="cta"
                                actionSize="comfortable"
                                className="w-full"
                            >
                                {t("plan.selectButton")}
                            </ActionLink>
                        </div>
                    </div>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
