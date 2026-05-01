import ErrorScreen from "@/Components/ErrorScreen";
import { Head, Link } from "@inertiajs/react";
import { PageProps } from "@/types";
import { t } from "@/i18n";
import { appRoutes } from "@/utils/routes";

interface ErrorPageProps extends PageProps {
    status: number;
}

const errorContents: Record<number, { title: string; description: string }> = {
    403: {
        title: t("error.forbidden.title"),
        description: t("error.forbidden.description"),
    },
    404: {
        title: t("error.notFound.title"),
        description: t("error.notFound.description"),
    },
    429: {
        title: t("error.rateLimit.title"),
        description: t("error.rateLimit.description"),
    },
    500: {
        title: t("error.serverError.title"),
        description: t("error.serverError.description"),
    },
    503: {
        title: t("error.unavailable.title"),
        description: t("error.unavailable.description"),
    },
    504: {
        title: t("error.timeout.title"),
        description: t("error.timeout.description"),
    },
};

export default function Error({ status }: ErrorPageProps) {
    const content = errorContents[status] ?? errorContents[500];

    return (
        <>
            <Head title={`${status} ${content.title}`} />

            <ErrorScreen
                badge={`Error ${status}`}
                title={content.title}
                description={content.description}
                actions={
                    <>
                        <Link
                            href={appRoutes.home()}
                            className="app-dark-cta-solid px-5 py-2 text-sm font-semibold"
                        >
                            {t("error.backToTop")}
                        </Link>
                        <button
                            type="button"
                            onClick={() => window.history.back()}
                            className="app-dark-cta-ghost px-5 py-2 text-sm font-semibold"
                        >
                            {t("error.backToPrev")}
                        </button>
                    </>
                }
            />
        </>
    );
}
