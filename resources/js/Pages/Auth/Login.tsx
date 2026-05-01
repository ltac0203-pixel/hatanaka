import ActionLink from "@/Components/ActionLink";
import Checkbox from "@/Components/Checkbox";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PrimaryButton from "@/Components/PrimaryButton";
import TextInput from "@/Components/TextInput";
import GuestLayout from "@/Layouts/GuestLayout";
import { Head, useForm } from "@inertiajs/react";
import { FormEventHandler } from "react";
import { PageProps } from "@/types";
import { t } from "@/i18n";

export default function Login({
    status,
}: PageProps<{
    status?: string;
}>) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: "",
        password: "",
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route("login"), {
            onFinish: () => reset("password"),
        });
    };

    return (
        <GuestLayout
            footer={
                <div className="text-center text-sm text-gray-600">
                    {t("auth.login.noAccount")}{" "}
                    <ActionLink href={route("register")} variant="underlined">
                        {t("auth.login.registerLink")}
                    </ActionLink>
                </div>
            }
        >
            <Head title={t("auth.login.title")} />

            {status && (
                <div className="mb-6 border-2 border-black bg-black px-4 py-3 text-sm font-medium text-white">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <InputLabel
                        htmlFor="email"
                        value={t("auth.login.emailLabel")}
                    />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData("email", e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <InputLabel
                        htmlFor="password"
                        value={t("auth.login.passwordLabel")}
                    />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData("password", e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="flex flex-col gap-4 border-y border-gray-200 py-4 sm:flex-row sm:items-center">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData("remember", e.target.checked)
                            }
                        />
                        <span className="ms-2 text-sm text-gray-600">
                            {t("auth.login.rememberMe")}
                        </span>
                    </label>
                </div>

                <div className="flex justify-end">
                    <PrimaryButton
                        className="w-full justify-center sm:w-auto"
                        disabled={processing}
                    >
                        {t("auth.login.loginButton")}
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
