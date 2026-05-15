import ActionLink from "@/Components/ActionLink";
import PrimaryButton from "@/Components/PrimaryButton";
import GuestLayout from "@/Layouts/GuestLayout";
import { Head, useForm } from "@inertiajs/react";
import { FormEventHandler } from "react";
import { PageProps } from "@/types";
import { t } from "@/i18n";
import { appRoutes } from "@/utils/routes";

export default function VerifyEmail({
    status,
}: PageProps<{ status?: string }>) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route("verification.send"));
    };

    return (
        <GuestLayout>
            <Head title={t("auth.verifyEmail.title")} />

            <h1 className="text-lg font-semibold uppercase tracking-wide text-black">
                {t("auth.verifyEmail.title")}
            </h1>
            <p className="mt-3 text-sm leading-relaxed text-gray-600">
                {t("auth.verifyEmail.description")}
            </p>

            {status === "verification-link-sent" && (
                <div className="mt-4 border-2 border-black bg-black px-4 py-3 text-sm font-medium text-white">
                    {t("auth.verifyEmail.linkSent")}
                </div>
            )}

            <form
                onSubmit={submit}
                className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <PrimaryButton
                    className="w-full justify-center sm:w-auto"
                    disabled={processing}
                >
                    {t("auth.verifyEmail.resendButton")}
                </PrimaryButton>

                <ActionLink
                    href={appRoutes.auth.logout()}
                    method="post"
                    as="button"
                    variant="underlined"
                    className="text-sm"
                >
                    {t("auth.verifyEmail.logoutButton")}
                </ActionLink>
            </form>
        </GuestLayout>
    );
}
