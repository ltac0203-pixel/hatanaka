import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import ActionLink from "@/Components/ActionLink";
import TextMark from "@/Components/TextMark";
import { Head } from "@inertiajs/react";
import { PageProps } from "@/types";
import { t } from "@/i18n";
import { appRoutes } from "@/utils/routes";

export default function Dashboard(_props: PageProps) {
    return (
        <AuthenticatedLayout
            header={<h2 className="app-page-title">{t("dashboard.title")}</h2>}
        >
            <Head title={t("dashboard.title")} />

            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <ActionLink
                    href={appRoutes.subscription.index()}
                    variant="panel"
                >
                    <div className="flex items-center">
                        <TextMark
                            label="S"
                            boxed
                            className="h-12 w-12 text-sm"
                        />
                        <div className="ml-4">
                            <h4 className="text-lg font-semibold uppercase tracking-wide">
                                {t("dashboard.subscription.label")}
                            </h4>
                            <p className="text-sm text-gray-500">
                                {t("dashboard.subscription.description")}
                            </p>
                        </div>
                    </div>
                </ActionLink>

                <ActionLink
                    href={appRoutes.plans.index()}
                    variant="panel"
                >
                    <div className="flex items-center">
                        <TextMark
                            label="P"
                            boxed
                            className="h-12 w-12 text-sm"
                        />
                        <div className="ml-4">
                            <h4 className="text-lg font-semibold uppercase tracking-wide">
                                {t("dashboard.plan.label")}
                            </h4>
                            <p className="text-sm text-gray-500">
                                {t("dashboard.plan.description")}
                            </p>
                        </div>
                    </div>
                </ActionLink>

                <ActionLink
                    href={appRoutes.cards.create()}
                    variant="panel"
                >
                    <div className="flex items-center">
                        <TextMark
                            label="C"
                            boxed
                            className="h-12 w-12 text-sm"
                        />
                        <div className="ml-4">
                            <h4 className="text-lg font-semibold uppercase tracking-wide">
                                {t("dashboard.card.label")}
                            </h4>
                            <p className="text-sm text-gray-500">
                                {t("dashboard.card.description")}
                            </p>
                        </div>
                    </div>
                </ActionLink>
            </div>
        </AuthenticatedLayout>
    );
}
