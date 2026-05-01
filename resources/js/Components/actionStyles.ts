export type ActionTone = "primary" | "secondary" | "danger";
export type ActionSize = "compact" | "comfortable";
export type ActionLinkVariant =
    | "cta"
    | "plain"
    | "underlined"
    | "subtle"
    | "panel"
    | "nav"
    | "responsiveNav"
    | "sidebar"
    | "menu";

interface ButtonStyleOptions {
    tone: ActionTone;
    size?: ActionSize;
    disabled?: boolean;
    className?: string;
}

interface LinkStyleOptions {
    variant: ActionLinkVariant;
    tone?: ActionTone;
    size?: ActionSize;
    active?: boolean;
    className?: string;
}

export function joinClassNames(
    ...classes: Array<string | false | null | undefined>
) {
    return classes.filter(Boolean).join(" ");
}

const ctaBaseClassName =
    "inline-flex cursor-pointer items-center justify-center border-2 font-semibold uppercase focus:outline-none focus:ring-2 focus:ring-offset-2";

const ctaSizeClassNames: Record<ActionSize, string> = {
    compact: "px-4 py-2 text-xs tracking-widest",
    comfortable: "px-4 py-2.5 text-sm tracking-wide",
};

const ctaToneClassNames: Record<ActionTone, string> = {
    primary:
        "border-black bg-black text-white focus:ring-black active:bg-gray-800 active:text-white",
    secondary:
        "border-black bg-transparent text-black focus:ring-black",
    danger:
        "border-red-600 bg-red-600 text-white focus:ring-red-500 active:bg-red-700",
};

export function getButtonClassName({
    tone,
    size = "compact",
    disabled = false,
    className = "",
}: ButtonStyleOptions) {
    return joinClassNames(
        ctaBaseClassName,
        ctaSizeClassNames[size],
        ctaToneClassNames[tone],
        disabled && "cursor-not-allowed opacity-25",
        className,
    );
}

export function getLinkClassName({
    variant,
    tone = "primary",
    size = "compact",
    active = false,
    className = "",
}: LinkStyleOptions) {
    switch (variant) {
        case "cta":
            return joinClassNames(
                ctaBaseClassName,
                ctaSizeClassNames[size],
                ctaToneClassNames[tone],
                className,
            );
        case "plain":
            return joinClassNames("inline-flex cursor-pointer text-black", className);
        case "underlined":
            return joinClassNames(
                "inline-flex cursor-pointer text-sm underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2",
                className,
            );
        case "subtle":
            return joinClassNames(
                "inline-flex cursor-pointer text-sm text-gray-500",
                className,
            );
        case "panel":
            return joinClassNames("app-panel cursor-pointer p-6", className);
        case "nav":
            return joinClassNames(
                "flex cursor-pointer items-center gap-3 px-3 py-2.5 text-sm font-medium uppercase tracking-wide",
                active
                    ? "border-2 border-white bg-white text-black"
                    : "border-2 border-transparent text-gray-400",
                className,
            );
        case "responsiveNav":
            return joinClassNames(
                "flex w-full cursor-pointer items-start border-l-4 py-2 pe-4 ps-3 text-base font-medium uppercase tracking-wide focus:outline-none",
                active
                    ? "border-black bg-gray-100 text-black"
                    : "border-transparent text-gray-600",
                className,
            );
        case "sidebar":
            return joinClassNames(
                "flex w-full cursor-pointer items-center gap-2 border-2 border-transparent px-3 py-2 text-sm text-gray-400",
                className,
            );
        case "menu":
            return joinClassNames(
                "block w-full cursor-pointer px-4 py-2 text-start text-sm leading-5 text-black focus:bg-gray-100 focus:outline-none",
                className,
            );
    }
}
