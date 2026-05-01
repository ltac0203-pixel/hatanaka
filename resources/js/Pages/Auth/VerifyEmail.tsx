import PrimaryButton from "@/Components/PrimaryButton";
import GuestLayout from "@/Layouts/GuestLayout";
import { Head, useForm } from "@inertiajs/react";
import { FormEventHandler } from "react";
import { PageProps } from "@/types";

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
            <Head title="メール認証" />

            <div className="mb-4 text-sm text-gray-600">
                ご登録ありがとうございます。メールアドレスの認証をお願いします。
                確認メールに記載されたリンクをクリックしてください。
            </div>

            {status === "verification-link-sent" && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    認証リンクを再送しました。
                </div>
            )}

            <form onSubmit={submit}>
                <div className="mt-4 flex items-center justify-between">
                    <PrimaryButton disabled={processing}>
                        認証メールを再送
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
