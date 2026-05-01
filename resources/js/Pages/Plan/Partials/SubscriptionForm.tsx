import ActionLink from "@/Components/ActionLink";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PrimaryButton from "@/Components/PrimaryButton";
import SelectInput from "@/Components/SelectInput";
import TextInput from "@/Components/TextInput";
import { useForm } from "@inertiajs/react";
import { FincodeCard, Plan } from "@/types/subscription";
import { FormEventHandler, useState } from "react";
import { t } from "@/i18n";
import { appRoutes } from "@/utils/routes";

interface SubscriptionFormProps {
    plan: Plan;
    cards: FincodeCard[];
    minimumStartDate: string;
}

type RequestErrors = Record<string, string | string[] | undefined>;

const inlineErrorKeys = new Set(["card_id", "start_date"]);

function extractSubmissionError(errors: RequestErrors): string | null {
    for (const [key, value] of Object.entries(errors)) {
        if (inlineErrorKeys.has(key)) {
            continue;
        }

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

    return null;
}

export default function SubscriptionForm({
    plan,
    cards,
    minimumStartDate,
}: SubscriptionFormProps) {
    // This form is only rendered when at least one saved card is available.
    const initialCardId =
        cards.find((card) => card.is_default)?.id ?? cards[0].id;

    const { data, setData, post, processing, errors } = useForm({
        fincode_plan_id: plan.fincode_plan_id,
        card_id: initialCardId,
        start_date: minimumStartDate,
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [submissionError, setSubmissionError] = useState<string | null>(null);
    const isProcessing = processing || isSubmitting;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        setIsSubmitting(true);
        setSubmissionError(null);

        post(appRoutes.subscription.store(), {
            onError: (formErrors) =>
                setSubmissionError(
                    extractSubmissionError(formErrors as RequestErrors),
                ),
            onFinish: () => setIsSubmitting(false),
        });
    };

    return (
        <form onSubmit={submit} className="mt-6 space-y-6">
            <InputError
                message={submissionError ?? undefined}
                className="rounded border border-red-200 bg-red-50 px-4 py-3 text-red-700"
                role="alert"
            />

            <div>
                <InputLabel
                    htmlFor="card_id"
                    value={t("subscriptionForm.cardSelectLabel")}
                />
                <SelectInput
                    id="card_id"
                    className="mt-1 block w-full"
                    value={data.card_id}
                    onChange={(e) => setData("card_id", Number(e.target.value))}
                    required
                >
                    {cards.map((card) => (
                        <option key={card.id} value={card.id}>
                            {card.display_name} -{" "}
                            {t("subscriptionForm.expiryPrefix")}{" "}
                            {card.expiry_display}
                            {card.is_default
                                ? ` ${t("subscriptionForm.defaultSuffix")}`
                                : ""}
                        </option>
                    ))}
                </SelectInput>
                <InputError message={errors.card_id} className="mt-2" />
            </div>

            <div>
                <InputLabel
                    htmlFor="start_date"
                    value={t("subscriptionForm.startDateLabel")}
                />
                <TextInput
                    id="start_date"
                    type="date"
                    value={data.start_date}
                    min={minimumStartDate}
                    className="mt-1 block w-full"
                    onChange={(e) => setData("start_date", e.target.value)}
                    required
                />
                <InputError message={errors.start_date} className="mt-2" />
            </div>

            <div className="flex items-center justify-end space-x-4">
                <ActionLink
                    href={appRoutes.plans.index()}
                    variant="subtle"
                >
                    {t("subscriptionForm.backToPlans")}
                </ActionLink>
                <PrimaryButton disabled={isProcessing}>
                    {t("subscriptionForm.registerButton")}
                </PrimaryButton>
            </div>
        </form>
    );
}
