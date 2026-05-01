import TextMark from "@/Components/TextMark";
import { Plan } from "@/types/subscription";
import { ReactNode } from "react";
import { toStableFeatureItems } from "@/utils/stableKeys";

interface PlanSummaryProps {
    plan: Plan;
    className?: string;
    descriptionClassName?: string;
    featureHeading?: ReactNode;
    featureSectionClassName?: string;
    featureListClassName?: string;
}

export default function PlanSummary({
    plan,
    className = "",
    descriptionClassName = "mt-4 text-sm text-gray-600",
    featureHeading,
    featureSectionClassName = "mt-4",
    featureListClassName = "space-y-2",
}: PlanSummaryProps) {
    const featureItems = plan.features ? toStableFeatureItems(plan.features) : [];

    return (
        <div className={className}>
            <h3 className="text-2xl font-bold uppercase tracking-wide text-black">
                {plan.name}
            </h3>
            <p className="mt-4 text-3xl font-bold text-black">
                {plan.price_display}
            </p>
            <p className="mt-1 text-sm text-gray-500">{plan.interval_label}</p>
            {plan.description && (
                <p className={descriptionClassName}>{plan.description}</p>
            )}
            {featureItems.length > 0 && (
                <div className={featureSectionClassName}>
                    {featureHeading}
                    <ul className={featureListClassName}>
                        {featureItems.map((feature) => (
                            <li
                                key={feature.key}
                                className="flex items-start text-sm text-gray-600"
                            >
                                <TextMark
                                    label="+"
                                    className="mr-2 mt-0.5 h-5 w-5 text-base text-black"
                                />
                                {feature.label}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
