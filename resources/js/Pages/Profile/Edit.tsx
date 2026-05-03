import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";

export default function Edit() {
    return (
        <AuthenticatedLayout>
            <Head title="プロフィール" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <h2 className="text-lg font-medium text-gray-900">
                            プロフィール設定
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            プロフィール情報の更新は準備中です。
                        </p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
