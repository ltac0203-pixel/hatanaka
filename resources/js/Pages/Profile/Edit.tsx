import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import DangerButton from "@/Components/DangerButton";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import Modal from "@/Components/Modal";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";
import TextInput from "@/Components/TextInput";
import { Head, useForm } from "@inertiajs/react";
import { FormEventHandler, useRef, useState } from "react";
import { PageProps } from "@/types";
import { t } from "@/i18n";

interface Props extends PageProps {}

export default function Edit({ auth }: Props) {
    const profileForm = useForm({
        name: auth.user?.name ?? "",
        email: auth.user?.email ?? "",
    });

    const submitProfile: FormEventHandler = (e) => {
        e.preventDefault();
        profileForm.patch(route("profile.update"));
    };

    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const deleteForm = useForm({ password: "" });
    const passwordInput = useRef<{ focus: () => void }>(null);

    const openDeleteModal = () => setShowDeleteModal(true);

    const closeDeleteModal = () => {
        setShowDeleteModal(false);
        deleteForm.reset("password");
    };

    const submitDelete: FormEventHandler = (e) => {
        e.preventDefault();
        deleteForm.delete(route("profile.destroy"), {
            preserveScroll: true,
            onSuccess: () => closeDeleteModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => deleteForm.reset("password"),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="app-page-title">{t("profile.title")}</h2>
            }
        >
            <Head title={t("profile.title")} />

            <div className="space-y-6">
                <div className="app-panel-padded">
                    <div>
                        <h3 className="text-lg font-semibold uppercase tracking-wide text-black">
                            {t("profile.updateProfileInformation.title")}
                        </h3>
                        <p className="mt-1 text-sm text-gray-600">
                            {t("profile.updateProfileInformation.description")}
                        </p>
                    </div>
                    <form onSubmit={submitProfile} className="mt-6 space-y-6">
                        <div>
                            <InputLabel
                                htmlFor="name"
                                value={t(
                                    "profile.updateProfileInformation.nameLabel",
                                )}
                            />
                            <TextInput
                                id="name"
                                className="mt-1 block w-full"
                                value={profileForm.data.name}
                                onChange={(e) =>
                                    profileForm.setData("name", e.target.value)
                                }
                                required
                                autoComplete="name"
                            />
                            <InputError
                                className="mt-2"
                                message={profileForm.errors.name}
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="email"
                                value={t(
                                    "profile.updateProfileInformation.emailLabel",
                                )}
                            />
                            <TextInput
                                id="email"
                                type="email"
                                className="mt-1 block w-full"
                                value={profileForm.data.email}
                                onChange={(e) =>
                                    profileForm.setData("email", e.target.value)
                                }
                                required
                                autoComplete="username"
                            />
                            <InputError
                                className="mt-2"
                                message={profileForm.errors.email}
                            />
                        </div>

                        <div className="flex items-center gap-4">
                            <PrimaryButton disabled={profileForm.processing}>
                                {t(
                                    "profile.updateProfileInformation.saveButton",
                                )}
                            </PrimaryButton>
                            {profileForm.recentlySuccessful && (
                                <p className="text-sm text-gray-600">
                                    {t(
                                        "profile.updateProfileInformation.saved",
                                    )}
                                </p>
                            )}
                        </div>
                    </form>
                </div>

                <div className="app-panel-padded">
                    <div>
                        <h3 className="text-lg font-semibold uppercase tracking-wide text-black">
                            {t("profile.deleteUser.title")}
                        </h3>
                        <p className="mt-1 text-sm text-gray-600">
                            {t("profile.deleteUser.description")}
                        </p>
                    </div>
                    <div className="mt-6">
                        <DangerButton onClick={openDeleteModal}>
                            {t("profile.deleteUser.deleteButton")}
                        </DangerButton>
                    </div>
                </div>
            </div>

            <Modal
                show={showDeleteModal}
                maxWidth="md"
                onClose={closeDeleteModal}
            >
                <form onSubmit={submitDelete} className="p-6">
                    <h2 className="text-lg font-semibold uppercase tracking-wide text-black">
                        {t("profile.deleteUser.modalTitle")}
                    </h2>
                    <p className="mt-2 text-sm text-gray-600">
                        {t("profile.deleteUser.modalDescription")}
                    </p>
                    <div className="mt-6">
                        <InputLabel
                            htmlFor="delete-password"
                            value={t("profile.deleteUser.passwordLabel")}
                            className="sr-only"
                        />
                        <TextInput
                            id="delete-password"
                            type="password"
                            ref={passwordInput}
                            value={deleteForm.data.password}
                            onChange={(e) =>
                                deleteForm.setData("password", e.target.value)
                            }
                            className="block w-full"
                            isFocused
                            placeholder={t("profile.deleteUser.passwordLabel")}
                        />
                        <InputError
                            message={deleteForm.errors.password}
                            className="mt-2"
                        />
                    </div>
                    <div className="mt-6 flex justify-end space-x-3">
                        <SecondaryButton
                            onClick={closeDeleteModal}
                            disabled={deleteForm.processing}
                        >
                            {t("profile.deleteUser.cancelButton")}
                        </SecondaryButton>
                        <DangerButton disabled={deleteForm.processing}>
                            {t("profile.deleteUser.confirmButton")}
                        </DangerButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
